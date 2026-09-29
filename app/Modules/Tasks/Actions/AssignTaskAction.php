<?php

namespace App\Modules\Tasks\Actions;

use App\Models\User;
use App\Modules\Tasks\Models\Task;
use InvalidArgumentException;

class AssignTaskAction
{
    public function execute(
        Task $task,
        User $assignee
    ): Task {
        if (! $assignee->isActive()) {
            throw new InvalidArgumentException(
                'مسئول تسک باید کاربر فعال باشد.'
            );
        }

        $isProjectMember = $task->project
            ->members()
            ->where('user_id', $assignee->id)
            ->exists();

        if (! $isProjectMember) {
            throw new InvalidArgumentException(
                'مسئول تسک باید عضو همان پروژه باشد.'
            );
        }

        $task->assignee_id = $assignee->id;
        $task->save();

        return $task->refresh()->load([
            'project',
            'assignee',
            'creator',
        ]);
    }
}