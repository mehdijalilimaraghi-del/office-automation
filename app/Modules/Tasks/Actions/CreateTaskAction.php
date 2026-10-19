<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Actions;

use App\Core\CustomFields\Services\CustomFieldService;
use App\Core\ExternalParties\Contracts\ExternalPartyServiceInterface;
use App\Core\Permissions\Enums\PermissionScope;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectSection;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskActivity;
use App\Modules\Tasks\Models\TaskAssignment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateTaskAction
{
    public function __construct(
        private readonly ExternalPartyServiceInterface $externalPartyService,
        private readonly CustomFieldService $customFieldService,
    ) {}

    public function execute(array $data, User $creator): Task
    {
        return DB::transaction(function () use ($data, $creator): Task {
            $project = isset($data['project_id'])
                ? Project::query()->find($data['project_id'])
                : null;

            if (isset($data['project_id']) && $project === null) {
                throw new InvalidArgumentException('پروژه انتخاب‌شده یافت نشد.');
            }

            if ($project !== null && ! $project->is_active) {
                throw new InvalidArgumentException('امکان ایجاد تسک در پروژه غیرفعال وجود ندارد.');
            }

            if ($project !== null && ! isset($data['section_id'])) {
                throw new InvalidArgumentException('برای ایجاد تسک در پروژه، انتخاب بخش الزامی است.');
            }

            $section = isset($data['section_id'])
                ? ProjectSection::query()->with('department.manager')->find($data['section_id'])
                : null;

            if (isset($data['section_id']) && $section === null) {
                throw new InvalidArgumentException('بخش انتخاب‌شده یافت نشد.');
            }

            if ($section !== null && ($project === null || (int) $section->project_id !== (int) $project->id)) {
                throw new InvalidArgumentException('بخش انتخاب‌شده متعلق به این پروژه نیست.');
            }

            $assignee = null;
            $delegateAssignee = null;
            $departmentId = $data['department_id'] ?? null;

            if ($section !== null) {
                $department = $section->department;
                if ($department === null || ! $department->is_active || $department->manager === null || ! $department->manager->isActive()) {
                    throw new InvalidArgumentException('برای واحد این بخش، مدیر فعال تعریف نشده است.');
                }

                $isProjectOwner = (int) $project->manager_id === (int) $creator->id
                    || (int) $project->created_by === (int) $creator->id;
                $isOrganizationTaskAdmin = in_array(
                    PermissionScope::Organization->value,
                    $creator->permissionScopes('tasks.create'),
                    true
                );
                $isDepartmentManager = (int) $department->manager_id === (int) $creator->id;

                if (! $isProjectOwner && ! $isOrganizationTaskAdmin
                    && ! ($isDepartmentManager && $section->manager_can_create_tasks)) {
                    throw new InvalidArgumentException('شما اجازه تعریف تسک در این بخش را ندارید.');
                }

                $assignee = $department->manager;
                $departmentId = $department->id;

                if ((bool) ($data['delegate_to_employee'] ?? false)) {
                    $delegateId = $data['delegate_assignee_id'] ?? null;
                    $delegateAssignee = $delegateId !== null ? User::query()->find($delegateId) : null;

                    if ($delegateAssignee === null || ! $delegateAssignee->isActive()) {
                        throw new InvalidArgumentException('برای ارجاع به کارمند، یک کارمند فعال انتخاب کنید.');
                    }

                    if (! $department->members()->where('users.id', $delegateAssignee->id)->exists()) {
                        throw new InvalidArgumentException('کارمند انتخاب‌شده عضو واحد مسئول این بخش نیست.');
                    }
                }
            } else {
                $assignee = isset($data['assignee_id'])
                    ? User::query()->find($data['assignee_id'])
                    : null;

                if (isset($data['assignee_id']) && $assignee === null) {
                    throw new InvalidArgumentException('مسئول تسک یافت نشد.');
                }

                if ($assignee !== null && ! $assignee->isActive()) {
                    throw new InvalidArgumentException('مسئول تسک باید کاربر فعال باشد.');
                }

                if ($project !== null && $assignee !== null) {
                    $isProjectMember = $project->members()->where('user_id', $assignee->id)->exists();
                    $isDefaultProjectDepartmentMember = $project->is_system_default
                        && $project->department_id !== null
                        && $project->department()
                            ->whereHas('members', fn ($memberQuery) => $memberQuery->where('users.id', $assignee->id))
                            ->exists();

                    if (! $isProjectMember && ! $isDefaultProjectDepartmentMember) {
                        throw new InvalidArgumentException('مسئول تسک باید عضو پروژه یا عضو واحد مربوط به پروژه باشد.');
                    }
                }
            }

            if ($delegateAssignee !== null && $assignee !== null && $delegateAssignee->id === $assignee->id) {
                throw new InvalidArgumentException('مدیر واحد و کارمند مقصد ارجاع نمی‌توانند یک نفر باشند.');
            }

            $this->validateDates($data);
            $this->externalPartyService->validateTaskContext($data);

            $reviewRequired = (bool) ($data['review_required'] ?? false);
            $initialStatus = $data['status'] ?? 'todo';
            if ($reviewRequired && in_array($initialStatus, ['completed', 'cancelled'], true)) {
                throw new InvalidArgumentException('دستورکار نیازمند بررسی باید ابتدا انجام شود و سپس برای بررسی مدیر ارسال گردد.');
            }

            $task = Task::query()->create([
                'project_id' => $project?->id,
                'section_id' => $section?->id,
                'department_id' => $departmentId,
                'external_organization_id' => $data['external_organization_id'] ?? null,
                'external_department_id' => $data['external_department_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'todo',
                'priority' => $data['priority'] ?? 'normal',
                'review_required' => $reviewRequired,
                'review_status' => $reviewRequired ? 'not_started' : 'not_required',
                'assignee_id' => $assignee?->id,
                'created_by' => $creator->id,
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'completed_at' => ($data['status'] ?? 'todo') === 'completed' ? now() : null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->customFieldService->sync($task, $data['custom_fields'] ?? []);

            if (! empty($data['next_activity_title'])) {
                TaskActivity::query()->create([
                    'task_id' => $task->id,
                    'title' => trim((string) $data['next_activity_title']),
                    'description' => $data['next_activity_description'] ?? null,
                    'status' => 'pending',
                    'activity_type' => 'planned',
                    'sequence' => 1,
                    'assigned_to' => $data['next_activity_assigned_to'] ?? $delegateAssignee?->id ?? $assignee?->id,
                    'created_by' => $creator->id,
                ]);
            }

            if ($assignee !== null) {
                $managerAssignment = TaskAssignment::query()->create([
                    'task_id' => $task->id,
                    'from_user_id' => null,
                    'to_user_id' => $assignee->id,
                    'assigned_by' => $creator->id,
                    'type' => 'assignment',
                    'note' => $data['assignment_note'] ?? null,
                    'started_at' => now(),
                ]);

                if ($delegateAssignee !== null) {
                    $managerAssignment->update(['ended_at' => now()]);
                    $task->update(['assignee_id' => $delegateAssignee->id]);

                    TaskAssignment::query()->create([
                        'task_id' => $task->id,
                        'from_user_id' => $assignee->id,
                        'to_user_id' => $delegateAssignee->id,
                        'assigned_by' => $creator->id,
                        'type' => 'referral',
                        'note' => $data['assignment_note'] ?? 'ارجاع اولیه توسط ادمین پروژه',
                        'started_at' => now(),
                    ]);
                }
            }

            return $task->refresh()->load(['project', 'section', 'department', 'assignee', 'creator']);
        });
    }

    private function validateDates(array $data): void
    {
        if (
            isset($data['start_date'], $data['due_date'])
            && $data['start_date'] !== null
            && $data['due_date'] !== null
            && $data['due_date'] < $data['start_date']
        ) {
            throw new InvalidArgumentException('تاریخ سررسید تسک نمی‌تواند قبل از تاریخ شروع باشد.');
        }
    }
}
