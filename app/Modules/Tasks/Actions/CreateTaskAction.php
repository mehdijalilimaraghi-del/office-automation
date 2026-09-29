<?php

namespace App\Modules\Tasks\Actions;

use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Tasks\Models\Task;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateTaskAction
{
    public function execute(
        array $data,
        User $creator
    ): Task {
        return DB::transaction(function () use (
            $data,
            $creator
        ): Task {
            $project = Project::query()
                ->find($data['project_id']);

            if ($project === null) {
                throw new InvalidArgumentException(
                    'پروژه مورد نظر یافت نشد.'
                );
            }

            if (! $project->is_active) {
                throw new InvalidArgumentException(
                    'امکان ایجاد تسک در پروژه غیرفعال وجود ندارد.'
                );
            }

            $assignee = User::query()
                ->find($data['assignee_id']);

            if ($assignee === null) {
                throw new InvalidArgumentException(
                    'مسئول تسک یافت نشد.'
                );
            }

            if (! $assignee->isActive()) {
                throw new InvalidArgumentException(
                    'مسئول تسک باید کاربر فعال باشد.'
                );
            }

            $isProjectMember = $project->members()
                ->where('user_id', $assignee->id)
                ->exists();

            if (! $isProjectMember) {
                throw new InvalidArgumentException(
                    'مسئول تسک باید عضو همان پروژه باشد.'
                );
            }

            $this->validateDates($data);

            $task = Task::query()->create([
                'project_id' => $project->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'todo',
                'priority' => $data['priority'] ?? 'normal',
                'assignee_id' => $assignee->id,
                'created_by' => $creator->id,
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'completed_at' => null,
                'is_active' => $data['is_active'] ?? true,
            ]);

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