<?php

namespace App\Modules\Tasks\Actions;

use App\Modules\Tasks\Models\Task;
use InvalidArgumentException;

class UpdateTaskAction
{
    public function execute(
        Task $task,
        array $data
    ): Task {
        $startDate = $data['start_date']
            ?? $task->start_date?->format('Y-m-d');

        $dueDate = $data['due_date']
            ?? $task->due_date?->format('Y-m-d');

        if (
            $startDate !== null &&
            $dueDate !== null &&
            $dueDate < $startDate
        ) {
            throw new InvalidArgumentException(
                'تاریخ سررسید تسک نمی‌تواند قبل از تاریخ شروع باشد.'
            );
        }

        $task->fill([
            'title' => $data['title'] ?? $task->title,
            'description' => array_key_exists(
                'description',
                $data
            )
                ? $data['description']
                : $task->description,
            'priority' => $data['priority'] ?? $task->priority,
            'start_date' => array_key_exists(
                'start_date',
                $data
            )
                ? $data['start_date']
                : $task->start_date,
            'due_date' => array_key_exists(
                'due_date',
                $data
            )
                ? $data['due_date']
                : $task->due_date,
            'is_active' => $data['is_active'] ?? $task->is_active,
        ]);

        $task->save();

        return $task->refresh()->load([
            'project',
            'assignee',
            'creator',
        ]);
    }
}