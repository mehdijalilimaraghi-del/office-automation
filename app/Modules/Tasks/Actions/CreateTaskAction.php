<?php

namespace App\Modules\Tasks\Actions;

use App\Models\User;
use App\Core\CustomFields\Services\CustomFieldService;
use App\Core\ExternalParties\Contracts\ExternalPartyServiceInterface;
use App\Modules\Projects\Models\Project;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskAssignment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateTaskAction
{
    public function __construct(private readonly ExternalPartyServiceInterface $externalPartyService) {}

    public function execute(
        array $data,
        User $creator
    ): Task {
        return DB::transaction(function () use (
            $data,
            $creator
        ): Task {
            $project = isset($data['project_id'])
                ? Project::query()->find($data['project_id'])
                : null;

            if ($project !== null && ! $project->is_active) {
                throw new InvalidArgumentException(
                    'امکان ایجاد تسک در پروژه غیرفعال وجود ندارد.'
                );
            }

            $assignee = isset($data['assignee_id'])
                ? User::query()->find($data['assignee_id'])
                : null;

            if (isset($data['assignee_id']) && $assignee === null) {
                throw new InvalidArgumentException(
                    'مسئول تسک یافت نشد.'
                );
            }

            if ($assignee !== null && ! $assignee->isActive()) {
                throw new InvalidArgumentException(
                    'مسئول تسک باید کاربر فعال باشد.'
                );
            }

            if ($project !== null && $assignee !== null) {
                $isProjectMember = $project->members()
                    ->where('user_id', $assignee->id)
                    ->exists();

                if (! $isProjectMember) {
                    throw new InvalidArgumentException(
                        'مسئول تسک باید عضو همان پروژه باشد.'
                    );
                }
            }

            $this->validateDates($data);
            $this->externalPartyService->validateTaskContext($data);

            $task = Task::query()->create([
                'project_id' => $project?->id,
                'department_id' => $data['department_id'] ?? null,
                'external_organization_id' => $data['external_organization_id'] ?? null,
                'external_department_id' => $data['external_department_id'] ?? null,
                'external_contact_id' => $data['external_contact_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'todo',
                'priority' => $data['priority'] ?? 'normal',
                'review_required' => (bool) ($data['review_required'] ?? false),
                'review_status' => ($data['review_required'] ?? false) ? 'not_started' : 'not_required',
                'assignee_id' => $assignee?->id,
                'created_by' => $creator->id,
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'completed_at' => ($data['status'] ?? 'todo') === 'completed' ? now() : null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->customFieldService->sync($task, $data['custom_fields'] ?? []);

            if ($assignee !== null) {
                TaskAssignment::query()->create([
                    'task_id' => $task->id,
                    'from_user_id' => null,
                    'to_user_id' => $assignee->id,
                    'assigned_by' => $creator->id,
                    'type' => 'assignment',
                    'note' => $data['assignment_note'] ?? null,
                    'started_at' => now(),
                ]);
            }

            return $task->load([
                'project',
                'assignee',
                'creator',
            ]);
        });
    }

    private function validateDates(array $data): void
    {
        if (
            isset($data['start_date']) &&
            isset($data['due_date']) &&
            $data['start_date'] !== null &&
            $data['due_date'] !== null &&
            $data['due_date'] < $data['start_date']
        ) {
            throw new InvalidArgumentException(
                'تاریخ سررسید تسک نمی‌تواند قبل از تاریخ شروع باشد.'
            );
        }
    }
}