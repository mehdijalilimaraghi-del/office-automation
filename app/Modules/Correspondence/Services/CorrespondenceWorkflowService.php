<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Services;

use App\Core\Audit\Contracts\AuditServiceInterface;
use App\Core\Departments\Models\Department;
use App\Core\Settings\Services\OrganizationDepartmentRoleService;
use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Models\CorrespondenceAction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CorrespondenceWorkflowService
{
    public function addCopies(Correspondence $correspondence, array $departmentIds, User $actor): void
    {
        $ids = collect($departmentIds)->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $validIds = Department::query()
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($validIds->count() !== $ids->count()) {
            throw new InvalidArgumentException('یکی از واحدهای انتخاب‌شده برای رونوشت فعال نیست یا وجود ندارد.');
        }

        DB::transaction(function () use ($correspondence, $validIds, $actor): void {
            $letter = Correspondence::query()->whereKey($correspondence->id)->lockForUpdate()->firstOrFail();

            foreach ($validIds as $departmentId) {
                $changes = $letter->copies()->syncWithoutDetaching([
                    $departmentId => ['created_by' => $actor->id, 'note' => 'جهت اطلاع'],
                ]);

                // Only record an action when a new copy recipient was actually attached.
                if (! in_array($departmentId, array_map('intval', $changes['attached'] ?? []), true)) {
                    continue;
                }

                $departmentName = (string) Department::query()->whereKey($departmentId)->value('name');
                $this->record(
                    $letter,
                    $actor,
                    CorrespondenceAction::COPY_ADDED,
                    'رونوشت جهت اطلاع به واحد '.$departmentName,
                    null,
                    $departmentId,
                    $actor->id,
                    null,
                );
            }
        });
    }

    public function handleAction(Correspondence $correspondence, User $actor, array $data): void
    {
        DB::transaction(function () use ($correspondence, $actor, $data): void {
            $letter = Correspondence::query()->whereKey($correspondence->id)->lockForUpdate()->firstOrFail();
            $type = (string) $data['action_type'];
            $note = trim((string) ($data['note'] ?? ''));

            if ($letter->workflow_state === 'completed_with_result') {
                throw new InvalidArgumentException('گردش این نامه پس از تأیید نتیجه خاتمه یافته است و اقدام جدیدی نمی‌پذیرد.');
            }
            $targetDepartmentId = isset($data['to_department_id']) ? (int) $data['to_department_id'] : null;
            $currentDepartmentId = $letter->current_department_id === null ? null : (int) $letter->current_department_id;
            $isManager = $currentDepartmentId !== null
                && (int) Department::query()->whereKey($currentDepartmentId)->value('manager_id') === (int) $actor->id;
            $isDepartmentMember = $currentDepartmentId !== null && (
                $actor->departments()->where('departments.id', $currentDepartmentId)->exists()
                || $actor->managedDepartments()->whereKey($currentDepartmentId)->exists()
            );

            if (! $actor->isActive() || ! $actor->hasPermission('correspondence.view')) {
                abort(403);
            }

            // Results apply to registered incoming/internal letters. Outgoing dispatch tracking is separate.
            $canRecordResult = $letter->status === Correspondence::STATUS_REGISTERED
                && in_array($letter->type, [Correspondence::TYPE_INCOMING, Correspondence::TYPE_INTERNAL], true);

            if ($type === CorrespondenceAction::SUBMIT_RESULT) {
                abort_unless($canRecordResult && $isDepartmentMember && ! $isManager, 403);
                if ($note === '') {
                    throw new InvalidArgumentException('برای ثبت نتیجه، شرح بررسی و نتیجه یا علت انجام‌نشدن درخواست الزامی است.');
                }
                $managerId = Department::query()->whereKey($currentDepartmentId)->value('manager_id');
                if ($managerId === null) {
                    throw new InvalidArgumentException('برای واحد فعلی مدیر مشخص نشده است.');
                }

                $this->record($letter, $actor, $type, $note, $currentDepartmentId, $currentDepartmentId, $actor->id, (int) $managerId);
                $letter->forceFill(['current_user_id' => (int) $managerId, 'workflow_state' => 'result_pending_manager'])->save();
                return;
            }

            abort_unless($isManager, 403);

            if ($type === CorrespondenceAction::MANAGER_APPROVE) {
                if ($letter->status !== Correspondence::STATUS_DRAFT || $letter->workflow_state !== 'awaiting_manager_approval') {
                    throw new InvalidArgumentException('این نامه در انتظار تأیید مدیر نیست.');
                }
                if (! in_array($letter->type, [Correspondence::TYPE_INTERNAL, Correspondence::TYPE_OUTGOING], true)) {
                    throw new InvalidArgumentException('این مرحله تأیید برای نامه داخلی یا صادره قابل استفاده است.');
                }
                $this->record($letter, $actor, $type, $note, $currentDepartmentId, null, $actor->id, null);
                $letter->forceFill(['workflow_state' => 'approved_for_registration', 'current_user_id' => null])->save();
                return;
            }

            if ($type === CorrespondenceAction::CONFIRM_RESULT) {
                abort_unless($canRecordResult && $letter->workflow_state === 'result_pending_manager', 403);
                if ($note === '') {
                    throw new InvalidArgumentException('برای تأیید نتیجه، توضیح یا جمع‌بندی مدیر الزامی است.');
                }
                $this->record($letter, $actor, $type, $note, $currentDepartmentId, null, $actor->id, null);
                $letter->forceFill(['workflow_state' => 'completed_with_result', 'current_user_id' => null])->save();
                return;
            }

            if (in_array($type, [CorrespondenceAction::MANAGER_REFER, CorrespondenceAction::REQUEST_TASK, CorrespondenceAction::REQUEST_PROJECT, CorrespondenceAction::REQUEST_LETTER], true)) {
                if ($targetDepartmentId === null) {
                    throw new InvalidArgumentException('برای ارجاع یا ثبت درخواست، واحد مقصد را انتخاب کنید.');
                }
                $target = Department::query()->whereKey($targetDepartmentId)->where('is_active', true)->first();
                if ($target === null) {
                    throw new InvalidArgumentException('واحد مقصد فعال نیست یا وجود ندارد.');
                }
                if ($type === CorrespondenceAction::REQUEST_PROJECT) {
                    abort_unless(app(OrganizationDepartmentRoleService::class)->isAssigned($target, OrganizationDepartmentRoleService::PROJECT_MANAGEMENT), 403);
                }

                $this->record($letter, $actor, $type, $note, $currentDepartmentId, $targetDepartmentId, $actor->id, null);
                $letter->forceFill([
                    'current_department_id' => $targetDepartmentId,
                    'current_user_id' => $target->manager_id,
                    'workflow_state' => match ($type) {
                        CorrespondenceAction::REQUEST_TASK => 'task_requested',
                        CorrespondenceAction::REQUEST_PROJECT => 'project_requested',
                        CorrespondenceAction::REQUEST_LETTER => 'letter_requested',
                        CorrespondenceAction::MANAGER_REFER => $letter->status === Correspondence::STATUS_DRAFT
                            && app(CorrespondenceCompositionSettings::class)->requiresApproval($letter->type)
                            ? 'awaiting_manager_approval'
                            : 'in_progress',
                        default => 'in_progress',
                    },
                ])->save();
                return;
            }

            throw new InvalidArgumentException('اقدام انتخاب‌شده پشتیبانی نمی‌شود.');
        });
    }

    public function onRegistered(Correspondence $correspondence, User $actor): void
    {
        DB::transaction(function () use ($correspondence, $actor): void {
            $letter = Correspondence::query()->whereKey($correspondence->id)->lockForUpdate()->firstOrFail();
            $this->record(
                $letter,
                $actor,
                CorrespondenceAction::REGISTERED,
                'ثبت نهایی با شماره '.$letter->registration_number,
                null,
                null,
                $actor->id,
                null,
            );

            if ($letter->type === Correspondence::TYPE_INCOMING && $letter->recipient_department_id !== null) {
                $targetId = (int) $letter->recipient_department_id;
                $targetManagerId = Department::query()->whereKey($targetId)->value('manager_id');
                $this->record($letter, $actor, CorrespondenceAction::SECRETARIAT_INITIAL_REFERRAL, 'ارجاع اولیه دبیرخانه پس از ثبت نهایی', null, $targetId, null, $targetManagerId === null ? null : (int) $targetManagerId);
                $letter->forceFill(['current_department_id' => $targetId, 'current_user_id' => $targetManagerId, 'workflow_state' => 'in_progress'])->save();
                return;
            }

            $letter->forceFill([
                'workflow_state' => $letter->type === Correspondence::TYPE_OUTGOING ? 'awaiting_dispatch' : 'in_progress',
                'current_department_id' => $letter->type === Correspondence::TYPE_OUTGOING
                    ? app(OrganizationDepartmentRoleService::class)->activeDepartmentId(OrganizationDepartmentRoleService::SECRETARIAT)
                    : $letter->recipient_department_id,
                'current_user_id' => null,
            ])->save();
        });
    }

    private function record(
        Correspondence $letter,
        User $actor,
        string $type,
        string $note,
        ?int $fromDepartmentId,
        ?int $toDepartmentId,
        ?int $fromUserId,
        ?int $toUserId,
    ): void {
        $action = CorrespondenceAction::query()->create([
            'correspondence_id' => $letter->id,
            'actor_id' => $actor->id,
            'action_type' => $type,
            'note' => $note !== '' ? $note : null,
            'from_department_id' => $fromDepartmentId,
            'to_department_id' => $toDepartmentId,
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
        ]);

        app(AuditServiceInterface::class)->record(
            'correspondence.action.'.$type,
            $letter,
            null,
            null,
            [
                'action_id' => (int) $action->id,
                'from_department_id' => $fromDepartmentId,
                'to_department_id' => $toDepartmentId,
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
            ],
        );
    }
}
