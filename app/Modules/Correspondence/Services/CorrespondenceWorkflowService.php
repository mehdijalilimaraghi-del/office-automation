<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Services;

use App\Core\Audit\Contracts\AuditServiceInterface;
use App\Core\Departments\Models\Department;
use App\Core\Settings\Services\OrganizationDepartmentRoleService;
use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Models\CorrespondenceAction;
use App\Modules\Tasks\Models\Task;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectSection;
use App\Modules\Notifications\Services\NotificationService;
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

            if (in_array($letter->workflow_state, ['completed_with_result', 'workflow_completed', 'letter_draft_pending_manager', 'letter_registered'], true)) {
                throw new InvalidArgumentException('گردش این نامه پس از تأیید نتیجه خاتمه یافته است و اقدام جدیدی نمی‌پذیرد.');
            }
            $targetDepartmentId = isset($data['to_department_id']) ? (int) $data['to_department_id'] : null;
            $targetUserId = isset($data['to_user_id']) ? (int) $data['to_user_id'] : null;
            $targetType = (string) ($data['target_type'] ?? 'department');
            $roles = app(OrganizationDepartmentRoleService::class);
            $isSecretariat = $actor->isSystemAdmin() || $roles->userBelongsTo($actor, OrganizationDepartmentRoleService::SECRETARIAT);
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

            $latestTaskRequest = $letter->actions()
                ->whereIn('action_type', [
                    CorrespondenceAction::EMPLOYEE_REQUEST_NONE,
                    CorrespondenceAction::EMPLOYEE_REQUEST_TASK,
                    CorrespondenceAction::EMPLOYEE_REQUEST_LETTER,
                    CorrespondenceAction::EMPLOYEE_REQUEST_WORKFLOW,
                    CorrespondenceAction::EMPLOYEE_REQUEST_PROJECT,
                ])
                ->latest('id')
                ->first();
            $latestTaskDecision = $letter->actions()
                ->where('action_type', CorrespondenceAction::MANAGER_CREATE_TASK)
                ->latest('id')
                ->first();
            $latestTaskDecisionMetadata = $latestTaskDecision?->metadata ?? [];
            $hasApprovedTaskInstruction = $latestTaskRequest !== null
                && in_array($latestTaskRequest->action_type, [
                    CorrespondenceAction::EMPLOYEE_REQUEST_TASK,
                    CorrespondenceAction::EMPLOYEE_REQUEST_WORKFLOW,
                ], true)
                && $latestTaskDecision !== null
                && (int) $latestTaskDecision->actor_id === (int) $actor->id
                && (int) $latestTaskDecision->id > (int) $latestTaskRequest->id
                && $letter->task_id !== null
                && (int) ($latestTaskDecisionMetadata['task_id'] ?? 0) === (int) $letter->task_id;
            $awaitingApprovedTaskInstruction = $latestTaskRequest !== null
                && in_array($latestTaskRequest->action_type, [
                    CorrespondenceAction::EMPLOYEE_REQUEST_TASK,
                    CorrespondenceAction::EMPLOYEE_REQUEST_WORKFLOW,
                ], true)
                && $latestTaskDecision !== null
                && (int) $latestTaskDecision->actor_id === (int) $actor->id
                && (int) $latestTaskDecision->id > (int) $latestTaskRequest->id
                && ! isset($latestTaskDecisionMetadata['task_id'])
                && $letter->task_id === null;

            $projectDepartmentId = $roles->activeDepartmentId(OrganizationDepartmentRoleService::PROJECT_MANAGEMENT);
            $isProjectDepartment = $projectDepartmentId !== null && $currentDepartmentId === $projectDepartmentId;

            $generalEmployeeRequestActions = [
                CorrespondenceAction::EMPLOYEE_REQUEST_NONE,
                CorrespondenceAction::EMPLOYEE_REQUEST_TASK,
                CorrespondenceAction::EMPLOYEE_REQUEST_LETTER,
                CorrespondenceAction::EMPLOYEE_REQUEST_WORKFLOW, // legacy requests are handled as task requests
                CorrespondenceAction::EMPLOYEE_REQUEST_PROJECT,
            ];

            if (in_array($type, $generalEmployeeRequestActions, true)) {
                abort_unless($canRecordResult && ! $isProjectDepartment && $isDepartmentMember && ! $isManager
                    && $letter->workflow_state === 'assigned_to_employee'
                    && (int) $letter->current_user_id === (int) $actor->id, 403);
                if ($note === '') {
                    throw new InvalidArgumentException('ثبت بازخورد برای ارسال درخواست به مدیر الزامی است.');
                }
                $managerId = Department::query()->whereKey($currentDepartmentId)->value('manager_id');
                if ($managerId === null || (int) $managerId === (int) $actor->id) {
                    throw new InvalidArgumentException('برای واحد فعلی مدیر مشخص و مستقلی تعریف نشده است.');
                }

                $this->record(
                    $letter,
                    $actor,
                    $type,
                    $note,
                    $currentDepartmentId,
                    $currentDepartmentId,
                    $actor->id,
                    (int) $managerId,
                    ['request_type' => str_replace('employee_request_', '', $type)],
                );
                $letter->forceFill([
                    'current_user_id' => (int) $managerId,
                    'workflow_state' => 'general_review_pending_manager',
                ])->save();
                return;
            }

            if (in_array($type, [
                CorrespondenceAction::MANAGER_REJECT_EMPLOYEE_REQUEST,
                CorrespondenceAction::MANAGER_CREATE_TASK,
                CorrespondenceAction::MANAGER_REFER_PROJECT_MANAGEMENT,
                CorrespondenceAction::MANAGER_CREATE_REQUESTED_LETTER,
                CorrespondenceAction::MANAGER_ASSIGN_LETTER_DRAFT,
            ], true)) {
                abort_unless($isManager && $letter->workflow_state === 'general_review_pending_manager', 403);
                if ($note === '') {
                    throw new InvalidArgumentException('برای تصمیم مدیر، ثبت توضیح الزامی است.');
                }
                $requestAction = $letter->actions()
                    ->whereIn('action_type', $generalEmployeeRequestActions)
                    ->latest('id')
                    ->first();
                if ($requestAction === null || (int) $requestAction->to_user_id !== (int) $actor->id) {
                    throw new InvalidArgumentException('درخواست معتبری از کارمند برای بررسی مدیر وجود ندارد.');
                }

                if ($type === CorrespondenceAction::MANAGER_CREATE_TASK) {
                    if (! in_array($requestAction->action_type, [CorrespondenceAction::EMPLOYEE_REQUEST_TASK, CorrespondenceAction::EMPLOYEE_REQUEST_WORKFLOW], true)) {
                        throw new InvalidArgumentException('ایجاد دستورکار فقط برای درخواست دستورکار کارمند مجاز است.');
                    }
                    if (! $actor->hasPermission('tasks.create') || ! $actor->hasPermission('tasks.view')) {
                        throw new InvalidArgumentException('برای ایجاد دستورکار مرتبط، مجوز مشاهده و ایجاد دستورکار لازم است.');
                    }
                    $generalProject = Project::query()
                        ->where('is_system_default', true)
                        ->where('is_active', true)
                        ->whereNull('department_id')
                        ->orderBy('id')
                        ->first();
                    $departmentSection = $generalProject === null ? null : ProjectSection::query()
                        ->where('project_id', $generalProject->id)
                        ->where('department_id', $currentDepartmentId)
                        ->where('manager_can_create_tasks', true)
                        ->first();
                    if ($generalProject === null || $departmentSection === null) {
                        throw new InvalidArgumentException('پروژه عمومی فعال یا بخش مجاز این واحد برای ایجاد دستورکار تعریف نشده است.');
                    }
                    $this->record(
                        $letter, $actor, $type, $note,
                        $currentDepartmentId, $currentDepartmentId,
                        $actor->id, null,
                    );
                    $letter->forceFill([
                        'current_user_id' => (int) $actor->id,
                        'workflow_state' => 'in_progress',
                    ])->save();
                    return;
                }

                if (in_array($type, [
                    CorrespondenceAction::MANAGER_CREATE_REQUESTED_LETTER,
                    CorrespondenceAction::MANAGER_ASSIGN_LETTER_DRAFT,
                ], true)) {
                    if ($requestAction->action_type !== CorrespondenceAction::EMPLOYEE_REQUEST_LETTER) {
                        throw new InvalidArgumentException('این تصمیم فقط برای درخواست ثبت نامه قابل استفاده است.');
                    }
                    if ($type === CorrespondenceAction::MANAGER_CREATE_REQUESTED_LETTER && ! $actor->hasPermission('correspondence.create')) {
                        throw new InvalidArgumentException('برای ایجاد نامه، مجوز ثبت مکاتبه لازم است.');
                    }
                    if ($type === CorrespondenceAction::MANAGER_CREATE_REQUESTED_LETTER) {
                        $this->record(
                            $letter, $actor, $type, $note,
                            $currentDepartmentId, $currentDepartmentId,
                            $actor->id, null,
                        );
                        $letter->forceFill([
                            'current_user_id' => (int) $actor->id,
                            'workflow_state' => 'letter_creation_by_manager',
                        ])->save();
                        return;
                    }

                    $draftEmployee = User::query()->whereKey($requestAction->actor_id)->where('status', 'active')->first();
                    if ($draftEmployee === null || ! $draftEmployee->hasPermission('correspondence.create')) {
                        throw new InvalidArgumentException('کارمند انتخاب‌شده مجوز ثبت پیش‌نویس نامه را ندارد.');
                    }
                    $this->record(
                        $letter, $actor, $type, $note,
                        $currentDepartmentId, $currentDepartmentId,
                        $actor->id, (int) $requestAction->actor_id,
                    );
                    $letter->forceFill([
                        'current_user_id' => (int) $requestAction->actor_id,
                        'workflow_state' => 'letter_draft_pending_employee',
                    ])->save();
                    return;
                }

                if ($type === CorrespondenceAction::MANAGER_REJECT_EMPLOYEE_REQUEST) {
                    if ($requestAction->action_type === CorrespondenceAction::EMPLOYEE_REQUEST_NONE) {
                        throw new InvalidArgumentException('برای درخواست «بدون درخواست»، مدیر باید نامه را ارجاع دهد؛ رد درخواست کاربرد ندارد.');
                    }
                    $this->record(
                        $letter, $actor, $type, $note,
                        $currentDepartmentId, $currentDepartmentId,
                        $actor->id, (int) $requestAction->actor_id,
                    );
                    $letter->forceFill([
                        'current_user_id' => (int) $requestAction->actor_id,
                        'workflow_state' => 'assigned_to_employee',
                    ])->save();
                    return;
                }

                if ($requestAction->action_type !== CorrespondenceAction::EMPLOYEE_REQUEST_PROJECT) {
                    throw new InvalidArgumentException('ارجاع به مدیریت پروژه‌ها فقط برای درخواست پروژه مجاز است.');
                }
                $projectDepartmentId = $roles->activeDepartmentId(OrganizationDepartmentRoleService::PROJECT_MANAGEMENT);
                $projectDepartment = $projectDepartmentId === null
                    ? null
                    : Department::query()->whereKey($projectDepartmentId)->where('is_active', true)->first();
                if ($projectDepartment === null || $projectDepartment->manager_id === null
                    || ! User::query()->whereKey($projectDepartment->manager_id)->where('status', 'active')->exists()) {
                    throw new InvalidArgumentException('واحد فعال و مدیر واحد مدیریت پروژه‌ها در تنظیمات سازمان مشخص نشده است.');
                }
                $this->record(
                    $letter, $actor, $type, $note,
                    $currentDepartmentId, (int) $projectDepartment->id,
                    $actor->id, (int) $projectDepartment->manager_id,
                );
                $letter->forceFill([
                    'current_department_id' => (int) $projectDepartment->id,
                    'current_user_id' => (int) $projectDepartment->manager_id,
                    'workflow_state' => 'in_progress',
                ])->save();
                return;
            }

            if ($type === CorrespondenceAction::SUBMIT_RESULT) {
                abort_unless($canRecordResult && $isDepartmentMember && ! $isManager
                    && $letter->workflow_state === 'assigned_to_employee'
                    && (int) $letter->current_user_id === (int) $actor->id, 403);
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

            if ($type === CorrespondenceAction::SECRETARIAT_RETURN) {
                abort_unless($isSecretariat
                    && $letter->type === Correspondence::TYPE_OUTGOING
                    && $letter->status === Correspondence::STATUS_REGISTERED
                    && $letter->workflow_state === 'awaiting_dispatch', 403,
                    'برگشت برای اصلاح فقط توسط دبیرخانه و پیش از ثبت ارسال نهایی مجاز است.');
                if ($note === '') {
                    throw new InvalidArgumentException('برای برگشت نامه، شرح ایراد و علت برگشت الزامی است.');
                }
                $originDepartmentId = $letter->created_department_id === null ? null : (int) $letter->created_department_id;
                if ($originDepartmentId === null) {
                    throw new InvalidArgumentException('واحد ایجادکننده نامه مشخص نیست؛ امکان برگشت برای اصلاح وجود ندارد.');
                }
                $this->record($letter, $actor, $type, $note, $currentDepartmentId, $originDepartmentId, $actor->id, (int) $letter->created_by);
                $letter->forceFill([
                    'current_department_id' => $originDepartmentId,
                    'current_user_id' => (int) $letter->created_by,
                    'workflow_state' => 'returned_for_correction',
                ])->save();
                return;
            }

            if ($type === CorrespondenceAction::RESUBMIT_TO_SECRETARIAT) {
                abort_unless($letter->type === Correspondence::TYPE_OUTGOING
                    && $letter->status === Correspondence::STATUS_REGISTERED
                    && $letter->workflow_state === 'returned_for_correction'
                    && ((int) $letter->created_by === (int) $actor->id
                        || ($letter->created_department_id !== null
                            && (int) Department::query()->whereKey($letter->created_department_id)->value('manager_id') === (int) $actor->id)),
                    403, 'ارسال مجدد برای بررسی فقط توسط ایجادکننده نامه یا مدیر واحد ایجادکننده مجاز است.');
                $secretariatDepartmentId = $roles->activeDepartmentId(OrganizationDepartmentRoleService::SECRETARIAT);
                if ($secretariatDepartmentId === null) {
                    throw new InvalidArgumentException('واحد فعال دبیرخانه در تنظیمات مشخص نشده است.');
                }
                $secretariatManagerId = Department::query()->whereKey($secretariatDepartmentId)->value('manager_id');
                $this->record($letter, $actor, $type, $note, $currentDepartmentId, (int) $secretariatDepartmentId, $actor->id, $secretariatManagerId === null ? null : (int) $secretariatManagerId);
                $letter->forceFill([
                    'current_department_id' => (int) $secretariatDepartmentId,
                    'current_user_id' => $secretariatManagerId === null ? null : (int) $secretariatManagerId,
                    'workflow_state' => 'awaiting_dispatch',
                ])->save();
                return;
            }

            $projectDepartmentId = $roles->activeDepartmentId(OrganizationDepartmentRoleService::PROJECT_MANAGEMENT);
            $isProjectDepartment = $projectDepartmentId !== null && $currentDepartmentId === $projectDepartmentId;
            $projectEmployeeActions = [
                CorrespondenceAction::PM_REQUEST_REFER,
                CorrespondenceAction::PM_REQUEST_PROJECT,
                CorrespondenceAction::PM_REQUEST_REJECT,
                CorrespondenceAction::PM_REQUEST_RETURN,
            ];

            if (in_array($type, $projectEmployeeActions, true)) {
                abort_unless($canRecordResult && $isProjectDepartment && ! $isManager
                    && $letter->workflow_state === 'assigned_to_employee'
                    && (int) $letter->current_user_id === (int) $actor->id, 403);
                if ($note === '') {
                    throw new InvalidArgumentException('ثبت بازخورد برای درخواست اقدام در واحد پروژه‌ها الزامی است.');
                }
                $managerId = Department::query()->whereKey($currentDepartmentId)->value('manager_id');
                if ($managerId === null) {
                    throw new InvalidArgumentException('برای واحد پروژه‌ها مدیر فعال مشخص نشده است.');
                }
                $target = null;
                if ($type === CorrespondenceAction::PM_REQUEST_REFER) {
                    $target = $targetDepartmentId === null ? null : Department::query()
                        ->whereKey($targetDepartmentId)->where('is_active', true)->first();
                    if ($target === null || (int) $target->id === $currentDepartmentId) {
                        throw new InvalidArgumentException('برای درخواست ارجاع، یک واحد فعال و متفاوت انتخاب کنید.');
                    }
                }
                $this->record(
                    $letter, $actor, $type, $note, $currentDepartmentId, null,
                    $actor->id, (int) $managerId,
                    $target === null ? null : ['requested_department_id' => (int) $target->id],
                );
                $letter->forceFill([
                    'current_user_id' => (int) $managerId,
                    'workflow_state' => 'project_review_pending_manager',
                ])->save();
                return;
            }

            if (in_array($type, [
                CorrespondenceAction::PM_MANAGER_APPROVE_REQUEST,
                CorrespondenceAction::PM_MANAGER_REJECT_REQUEST,
            ], true)) {
                abort_unless($isManager && $isProjectDepartment
                    && $letter->workflow_state === 'project_review_pending_manager', 403);
                if ($note === '') {
                    throw new InvalidArgumentException('ثبت توضیح مدیر برای تصمیم درباره درخواست کارمند الزامی است.');
                }
                $requestAction = $letter->actions()
                    ->whereIn('action_type', $projectEmployeeActions)
                    ->latest('id')
                    ->first();
                if ($requestAction === null || (int) $requestAction->to_user_id !== (int) $actor->id) {
                    throw new InvalidArgumentException('درخواست معتبری برای بررسی مدیر واحد پروژه‌ها وجود ندارد.');
                }
                if ($type === CorrespondenceAction::PM_MANAGER_REJECT_REQUEST) {
                    $this->record($letter, $actor, $type, $note, $currentDepartmentId, $currentDepartmentId, $actor->id, (int) $requestAction->actor_id);
                    $letter->forceFill([
                        'current_user_id' => (int) $requestAction->actor_id,
                        'workflow_state' => 'assigned_to_employee',
                    ])->save();
                    return;
                }

                $requestedType = (string) $requestAction->action_type;
                if ($requestedType === CorrespondenceAction::PM_REQUEST_PROJECT) {
                    $this->record($letter, $actor, $type, $note, $currentDepartmentId, null, $actor->id, null);
                    $letter->forceFill([
                        'current_user_id' => (int) $actor->id,
                        'workflow_state' => 'project_request_approved',
                    ])->save();
                    return;
                }

                if (in_array($requestedType, [CorrespondenceAction::PM_REQUEST_REJECT, CorrespondenceAction::PM_REQUEST_RETURN], true)) {
                    $originDepartmentId = $letter->recipient_department_id === null ? null : (int) $letter->recipient_department_id;
                    if ($originDepartmentId === null) {
                        throw new InvalidArgumentException('واحد مبدأ نامه مشخص نیست و امکان بازگشت وجود ندارد.');
                    }
                    $origin = Department::query()->whereKey($originDepartmentId)->where('is_active', true)->first();
                    if ($origin === null) {
                        throw new InvalidArgumentException('واحد مبدأ نامه غیرفعال است یا وجود ندارد.');
                    }
                    $originManagerId = $origin->manager_id === null ? null : (int) $origin->manager_id;
                    $this->record($letter, $actor, $type, $note, $currentDepartmentId, $originDepartmentId, $actor->id, $originManagerId);
                    $letter->forceFill([
                        'current_department_id' => $originDepartmentId,
                        'current_user_id' => $originManagerId,
                        'workflow_state' => 'in_progress',
                    ])->save();
                    return;
                }

                if ($requestedType === CorrespondenceAction::PM_REQUEST_REFER) {
                    $requestedDepartmentId = (int) ($requestAction->metadata['requested_department_id'] ?? 0);
                    $target = $requestedDepartmentId > 0
                        ? Department::query()->whereKey($requestedDepartmentId)->where('is_active', true)->first()
                        : null;
                    if ($target === null) {
                        throw new InvalidArgumentException('واحد مقصد درخواست ارجاع دیگر فعال نیست؛ کارمند باید درخواست را اصلاح کند.');
                    }
                    $targetManagerId = $target->manager_id === null ? null : (int) $target->manager_id;
                    $this->record($letter, $actor, $type, $note, $currentDepartmentId, (int) $target->id, $actor->id, $targetManagerId);
                    $letter->forceFill([
                        'current_department_id' => (int) $target->id,
                        'current_user_id' => $targetManagerId,
                        'workflow_state' => 'in_progress',
                    ])->save();
                    return;
                }
                throw new InvalidArgumentException('نوع درخواست ثبت‌شده برای تصمیم مدیر معتبر نیست.');
            }

            abort_unless($isManager, 403);

            if ($type === CorrespondenceAction::MANAGER_APPROVE) {
                if ($letter->status !== Correspondence::STATUS_DRAFT || $letter->workflow_state !== 'awaiting_manager_approval') {
                    throw new InvalidArgumentException('این نامه در انتظار تأیید مدیر نیست.');
                }
                if (! in_array($letter->type, [Correspondence::TYPE_INCOMING, Correspondence::TYPE_INTERNAL, Correspondence::TYPE_OUTGOING], true)
                    || ! app(CorrespondenceCompositionSettings::class)->requiresApproval($letter->type)) {
                    throw new InvalidArgumentException('تأیید مدیر فقط برای نوع نامه‌ای مجاز است که در تنظیمات سازمان نیاز به تأیید دارد.');
                }
                $this->record($letter, $actor, $type, $note, $currentDepartmentId, null, $actor->id, null);
                $letter->forceFill([
                    'workflow_state' => 'approved_for_registration',
                    // After approval, the Secretariat draft creator can complete registration.
                    'current_user_id' => $letter->type === Correspondence::TYPE_INCOMING ? (int) $letter->created_by : null,
                ])->save();
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

            if ($type === CorrespondenceAction::MANAGER_COMPLETE_WORKFLOW) {
                abort_unless(
                    $isManager
                    && $canRecordResult
                    && $letter->workflow_state === 'in_progress'
                    && $hasApprovedTaskInstruction,
                    403,
                    'خاتمه گردش فقط پس از ایجاد دستورکار تأییدشده توسط مدیر مسئول مجاز است.',
                );
                if ($note === '') {
                    throw new InvalidArgumentException('برای خاتمه گردش نامه، ثبت توضیح الزامی است.');
                }
                $this->record(
                    $letter,
                    $actor,
                    $type,
                    $note,
                    $currentDepartmentId,
                    null,
                    $actor->id,
                    null,
                    ['task_id' => (int) $letter->task_id],
                );
                $letter->forceFill([
                    'workflow_state' => 'workflow_completed',
                    'current_user_id' => null,
                ])->save();
                return;
            }

            if ($type === CorrespondenceAction::MANAGER_REFER && $awaitingApprovedTaskInstruction) {
                throw new InvalidArgumentException('ابتدا دستورکار مورد تأیید را ایجاد کنید؛ پس از اتصال دستورکار امکان ارجاع نامه فراهم می‌شود.');
            }

            if ($type === CorrespondenceAction::MANAGER_REFER
                && $isProjectDepartment
                && $targetType === 'department') {
                throw new InvalidArgumentException('ارجاع نامه درخواست پروژه به واحد دیگر فقط پس از ثبت درخواست ارجاع توسط کارمند واحد پروژه‌ها و تأیید مدیر مجاز است.');
            }

            if ($type === CorrespondenceAction::MANAGER_REFER
                && $letter->workflow_state === 'assigned_to_employee') {
                throw new InvalidArgumentException('نامه در حال حاضر به کارمند ارجاع شده است؛ برای بازگرداندن آن به مدیر از اقدام «پس گرفتن ارجاع» استفاده کنید.');
            }

            if ($type === CorrespondenceAction::MANAGER_RECALL_REFERRAL) {
                abort_unless($isManager
                    && $letter->workflow_state === 'assigned_to_employee'
                    && $letter->current_user_id !== null
                    && (int) $letter->current_user_id !== (int) $actor->id, 403);
                if ($note === '') {
                    throw new InvalidArgumentException('برای پس گرفتن ارجاع، ثبت دلیل الزامی است.');
                }
                $latestReferral = $letter->actions()
                    ->where('action_type', CorrespondenceAction::MANAGER_REFER)
                    ->where('to_user_id', (int) $letter->current_user_id)
                    ->latest('id')
                    ->first();
                if ($latestReferral === null || (int) $latestReferral->from_department_id !== $currentDepartmentId) {
                    throw new InvalidArgumentException('ارجاع فعالی برای پس گرفتن در این واحد پیدا نشد.');
                }
                $this->record(
                    $letter, $actor, $type, $note,
                    $currentDepartmentId, $currentDepartmentId,
                    (int) $letter->current_user_id, (int) $actor->id,
                    ['recalled_action_id' => (int) $latestReferral->id],
                );
                $letter->forceFill([
                    'current_user_id' => (int) $actor->id,
                    'workflow_state' => 'in_progress',
                ])->save();
                return;
            }

            if ($type === CorrespondenceAction::MANAGER_REFER && $targetType === 'user') {
                if ($targetUserId === null) {
                    throw new InvalidArgumentException('کاربر مقصد را انتخاب کنید.');
                }
                $targetUser = User::query()->whereKey($targetUserId)->where('status', 'active')->first();
                $isTargetMember = $targetUser !== null && (
                    $targetUser->departments()->where('departments.id', $currentDepartmentId)->exists()
                    || $targetUser->managedDepartments()->whereKey($currentDepartmentId)->exists()
                );
                if (! $isTargetMember) {
                    throw new InvalidArgumentException('کاربر انتخاب‌شده عضو واحد مسئول فعلی نیست یا فعال نمی‌باشد.');
                }
                $this->record($letter, $actor, $type, $note, $currentDepartmentId, $currentDepartmentId, $actor->id, $targetUserId);
                $letter->forceFill([
                    'current_department_id' => $currentDepartmentId,
                    'current_user_id' => $targetUserId,
                    'workflow_state' => 'assigned_to_employee',
                ])->save();
                return;
            }

            if ($type === CorrespondenceAction::MANAGER_REFER && $targetType === 'department') {
                if ($targetDepartmentId === null) {
                    throw new InvalidArgumentException('واحد مقصد را انتخاب کنید.');
                }
                $target = Department::query()->whereKey($targetDepartmentId)->where('is_active', true)->first();
                if ($target === null) {
                    throw new InvalidArgumentException('واحد مقصد فعال نیست یا وجود ندارد.');
                }
                $this->record($letter, $actor, $type, $note, $currentDepartmentId, $targetDepartmentId, $actor->id, $target->manager_id === null ? null : (int) $target->manager_id);
                $letter->forceFill([
                    'current_department_id' => $targetDepartmentId,
                    'current_user_id' => $target->manager_id,
                    'workflow_state' => $letter->status === Correspondence::STATUS_DRAFT
                        && app(CorrespondenceCompositionSettings::class)->requiresApproval($letter->type)
                        ? 'awaiting_manager_approval'
                        : 'in_progress',
                ])->save();
                return;
            }

            throw new InvalidArgumentException('اقدام انتخاب‌شده پشتیبانی نمی‌شود.');
        });
    }

    public function attachTaskInstruction(Correspondence $correspondence, Task $task, User $actor): void
    {
        DB::transaction(function () use ($correspondence, $task, $actor): void {
            $letter = Correspondence::query()->whereKey($correspondence->id)->lockForUpdate()->firstOrFail();
            $task->loadMissing(['project', 'section']);

            $departmentId = $letter->current_department_id === null ? null : (int) $letter->current_department_id;
            $isManager = $departmentId !== null
                && (int) Department::query()->whereKey($departmentId)->value('manager_id') === (int) $actor->id;

            if (! $isManager
                || $letter->status !== Correspondence::STATUS_REGISTERED
                || ! in_array($letter->type, [Correspondence::TYPE_INCOMING, Correspondence::TYPE_INTERNAL], true)
                || $letter->workflow_state !== 'in_progress'
                || ($letter->current_user_id !== null && (int) $letter->current_user_id !== (int) $actor->id)) {
                abort(403, 'فقط مدیر مسئول نامه در مرحله بررسی اولیه می‌تواند دستورکار مرتبط ایجاد کند.');
            }

            $latestTaskRequest = $letter->actions()
                ->whereIn('action_type', [
                    CorrespondenceAction::EMPLOYEE_REQUEST_NONE,
                    CorrespondenceAction::EMPLOYEE_REQUEST_TASK,
                    CorrespondenceAction::EMPLOYEE_REQUEST_LETTER,
                    CorrespondenceAction::EMPLOYEE_REQUEST_WORKFLOW,
                    CorrespondenceAction::EMPLOYEE_REQUEST_PROJECT,
                ])
                ->latest('id')
                ->first();
            $latestTaskApproval = $letter->actions()
                ->where('action_type', CorrespondenceAction::MANAGER_CREATE_TASK)
                ->latest('id')
                ->first();
            $latestTaskApprovalMetadata = $latestTaskApproval?->metadata ?? [];
            if ($latestTaskRequest === null
                || ! in_array($latestTaskRequest->action_type, [
                    CorrespondenceAction::EMPLOYEE_REQUEST_TASK,
                    CorrespondenceAction::EMPLOYEE_REQUEST_WORKFLOW,
                ], true)
                || $latestTaskApproval === null
                || (int) $latestTaskApproval->actor_id !== (int) $actor->id
                || (int) $latestTaskApproval->id <= (int) $latestTaskRequest->id
                || isset($latestTaskApprovalMetadata['task_id'])) {
                abort(403, 'ابتدا باید درخواست ایجاد دستورکار توسط مدیر تأیید شود.');
            }

            if ($letter->task_id !== null) {
                throw new InvalidArgumentException('برای این نامه قبلاً دستورکار ثبت شده است.');
            }

            if ((int) $task->created_by !== (int) $actor->id
                || ! $task->project?->is_system_default
                || $task->section === null
                || ! $task->section->manager_can_create_tasks
                || (int) $task->section->department_id !== $departmentId
                || (int) $task->department_id !== $departmentId
                || (int) $task->project_id !== (int) $task->section->project_id
                || $task->assignee_id === null) {
                throw new InvalidArgumentException('دستورکار باید در پروژه عمومی و بخش واحد مسئول همین نامه ایجاد شده و دقیقاً یک مسئول داشته باشد.');
            }

            $this->record(
                $letter,
                $actor,
                'manager_create_task',
                'ایجاد دستورکار عمومی مرتبط با نامه',
                $departmentId,
                $departmentId,
                $actor->id,
                (int) $task->assignee_id,
                [
                    'task_id' => (int) $task->id,
                    'project_id' => (int) $task->project_id,
                    'section_id' => (int) $task->section_id,
                ],
            );

            $letter->forceFill(['task_id' => (int) $task->id])->save();
        });
    }

    public function onRequestedLetterDraftCreated(Correspondence $source, Correspondence $draft, User $actor): void
    {
        DB::transaction(function () use ($source, $draft, $actor): void {
            $letter = Correspondence::query()->whereKey($source->id)->lockForUpdate()->firstOrFail();
            $departmentId = $letter->current_department_id === null ? null : (int) $letter->current_department_id;
            $managerId = $departmentId === null ? null : Department::query()->whereKey($departmentId)->value('manager_id');

            if ($draft->id === $letter->id || (int) $draft->reply_to_id !== (int) $letter->id
                || (int) $draft->created_by !== (int) $actor->id
                || (int) $draft->created_department_id !== (int) $departmentId
                || $draft->status !== Correspondence::STATUS_DRAFT
                || $managerId === null || (int) $letter->current_user_id !== (int) $actor->id) {
                abort(403, 'پیش‌نویس باید از مسیر درخواست همین نامه و توسط مسئول فعلی ایجاد شود.');
            }

            $isManagerDraft = $letter->workflow_state === 'letter_creation_by_manager'
                && (int) $managerId === (int) $actor->id;
            $isEmployeeDraft = $letter->workflow_state === 'letter_draft_pending_employee'
                && (int) $managerId !== (int) $actor->id;
            if (! $isManagerDraft && ! $isEmployeeDraft) {
                abort(403, 'این نامه در انتظار تهیه پیش‌نویس نیست.');
            }

            $this->record(
                $letter,
                $actor,
                CorrespondenceAction::REQUESTED_LETTER_DRAFT_CREATED,
                'پیش‌نویس نامه مرتبط ایجاد شد: '.$draft->subject,
                $departmentId,
                $departmentId,
                $actor->id,
                $isEmployeeDraft ? (int) $managerId : null,
                ['draft_correspondence_id' => (int) $draft->id],
            );

            $letter->forceFill([
                'current_user_id' => (int) $managerId,
                'workflow_state' => 'letter_draft_pending_manager',
            ])->save();
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

            if ($letter->reply_to_id !== null) {
                $source = Correspondence::query()->whereKey($letter->reply_to_id)->lockForUpdate()->first();
                $requestedDraftAction = $source?->actions()
                    ->where('action_type', CorrespondenceAction::REQUESTED_LETTER_DRAFT_CREATED)
                    ->latest('id')
                    ->first();
                $isRequestedDraft = $requestedDraftAction !== null
                    && (int) ($requestedDraftAction->metadata['draft_correspondence_id'] ?? 0) === (int) $letter->id;
                if ($source !== null && $source->workflow_state === 'letter_draft_pending_manager' && $isRequestedDraft) {
                    $this->record(
                        $source,
                        $actor,
                        CorrespondenceAction::REQUESTED_LETTER_REGISTERED,
                        'نامه پاسخ مرتبط با شماره '.$letter->registration_number.' ثبت نهایی شد.',
                        $source->current_department_id === null ? null : (int) $source->current_department_id,
                        null,
                        $actor->id,
                        null,
                        ['reply_correspondence_id' => (int) $letter->id],
                    );
                    $source->forceFill([
                        'workflow_state' => 'letter_registered',
                        'current_user_id' => null,
                    ])->save();
                }
            }

            if ($letter->type === Correspondence::TYPE_INCOMING && $letter->recipient_department_id !== null) {
                $targetId = (int) $letter->recipient_department_id;
                $targetManagerId = Department::query()->whereKey($targetId)->value('manager_id');
                $this->record($letter, $actor, CorrespondenceAction::SECRETARIAT_INITIAL_REFERRAL, 'ارجاع اولیه دبیرخانه پس از ثبت نهایی', null, $targetId, null, $targetManagerId === null ? null : (int) $targetManagerId);
                $letter->forceFill(['current_department_id' => $targetId, 'current_user_id' => $targetManagerId, 'workflow_state' => 'in_progress'])->save();
                return;
            }

            $targetDepartmentId = $letter->type === Correspondence::TYPE_OUTGOING
                ? app(OrganizationDepartmentRoleService::class)->activeDepartmentId(OrganizationDepartmentRoleService::SECRETARIAT)
                : ($letter->recipient_department_id === null ? null : (int) $letter->recipient_department_id);

            $letter->forceFill([
                'workflow_state' => $letter->type === Correspondence::TYPE_OUTGOING ? 'awaiting_dispatch' : 'in_progress',
                'current_department_id' => $targetDepartmentId,
                'current_user_id' => null,
            ])->save();

            $managerId = $targetDepartmentId === null
                ? null
                : Department::query()->whereKey($targetDepartmentId)->value('manager_id');
            if ($managerId !== null) {
                $recipient = User::query()->whereKey($managerId)->where('status', 'active')->first();
                if ($recipient !== null) {
                    app(NotificationService::class)->correspondenceAction(
                        $letter,
                        $recipient,
                        $letter->type === Correspondence::TYPE_OUTGOING ? 'correspondence.awaiting_dispatch' : 'correspondence.referred',
                        $letter->type === Correspondence::TYPE_OUTGOING
                            ? 'نامه صادره در انتظار ثبت ارسال دبیرخانه است'
                            : 'نامه جدید به واحد شما ارجاع شد',
                    );
                }
            }
        });
    }

    public function onDispatched(Correspondence $correspondence, User $actor): void
    {
        DB::transaction(function () use ($correspondence, $actor): void {
            $letter = Correspondence::query()->whereKey($correspondence->id)->lockForUpdate()->firstOrFail();

            if ($letter->type !== Correspondence::TYPE_OUTGOING
                || $letter->status !== Correspondence::STATUS_REGISTERED
                || $letter->sent_at === null) {
                throw new InvalidArgumentException('ثبت ارسال نهایی فقط برای نامه صادره ثبت‌شده مجاز است.');
            }

            if ($letter->workflow_state === 'dispatched') {
                return;
            }

            if ($letter->workflow_state !== 'awaiting_dispatch') {
                throw new InvalidArgumentException('این نامه در وضعیت انتظار برای ارسال دبیرخانه نیست.');
            }

            $this->record(
                $letter,
                $actor,
                CorrespondenceAction::DISPATCHED,
                'ارسال نهایی از دبیرخانه ثبت شد',
                $letter->current_department_id === null ? null : (int) $letter->current_department_id,
                null,
                $actor->id,
                (int) $letter->created_by,
            );

            $letter->forceFill([
                'workflow_state' => 'dispatched',
                'current_department_id' => null,
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
        ?array $metadata = null,
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
            'metadata' => $metadata,
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

        if ($toUserId !== null) {
            $recipient = User::query()->whereKey($toUserId)->where('status', 'active')->first();
            if ($recipient !== null) {
                app(NotificationService::class)->correspondenceAction(
                    $letter,
                    $recipient,
                    'correspondence.'.$type,
                    match ($type) {
                        CorrespondenceAction::SUBMIT_RESULT => 'نتیجه نامه برای بررسی شما ارسال شد',
                        CorrespondenceAction::MANAGER_REFER => 'نامه‌ای برای اقدام به شما ارجاع شد',
                        CorrespondenceAction::REQUEST_TASK => 'درخواست تسک مرتبط با نامه برای شما ارسال شد',
                        CorrespondenceAction::REQUEST_PROJECT => 'درخواست پروژه مرتبط با نامه برای شما ارسال شد',
                        CorrespondenceAction::REQUEST_LETTER => 'درخواست مکاتبه مرتبط برای شما ارسال شد',
                        CorrespondenceAction::DISPATCHED => 'ارسال نهایی نامه ثبت شد',
                        CorrespondenceAction::SECRETARIAT_RETURN => 'نامه صادره برای اصلاح به واحد ایجادکننده برگشت داده شد',
                        CorrespondenceAction::RESUBMIT_TO_SECRETARIAT => 'نامه اصلاح‌شده برای بررسی مجدد به دبیرخانه ارسال شد',
                        default => 'نامه‌ای نیازمند اقدام شماست',
                    },
                );
            }
        }

        $followUpUserId = match ($type) {
            CorrespondenceAction::MANAGER_APPROVE => (int) $letter->created_by,
            CorrespondenceAction::CONFIRM_RESULT => CorrespondenceAction::query()
                ->where('correspondence_id', $letter->id)
                ->where('action_type', CorrespondenceAction::SUBMIT_RESULT)
                ->latest('id')
                ->value('actor_id'),
            default => null,
        };
        if ($followUpUserId !== null) {
            $followUpUserId = (int) $followUpUserId;
        }

        if ($followUpUserId !== null && $followUpUserId !== (int) $actor->id && $followUpUserId !== $toUserId) {
            $followUpUser = User::query()->whereKey($followUpUserId)->where('status', 'active')->first();
            if ($followUpUser !== null) {
                app(NotificationService::class)->correspondenceAction(
                    $letter,
                    $followUpUser,
                    'correspondence.'.$type,
                    $type === CorrespondenceAction::MANAGER_APPROVE
                        ? 'نامه شما برای ثبت نهایی تأیید شد'
                        : 'نتیجه نامه توسط مدیر تأیید شد',
                );
            }
        }
    }
}
