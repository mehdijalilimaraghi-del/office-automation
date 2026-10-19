<?php

namespace App\Modules\Tasks\Actions;

use App\Modules\Tasks\Models\Task;
use App\Core\CustomFields\Services\CustomFieldService;
use App\Core\ExternalParties\Contracts\ExternalPartyServiceInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateTaskAction
{
    public function __construct(
        private readonly ExternalPartyServiceInterface $externalPartyService,
        private readonly CustomFieldService $customFieldService,
    ) {}

    public function execute(
        Task $task,
        array $data
    ): Task {
        if ($task->isFinalized()) {
            throw new InvalidArgumentException('دستورکار نهایی‌شده امکان ویرایش ندارد.');
        }

        return DB::transaction(function () use ($task, $data): Task {
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

            $this->externalPartyService->validateTaskContext([
            'external_organization_id' => array_key_exists('external_organization_id', $data) ? $data['external_organization_id'] : $task->external_organization_id,
            'external_department_id' => array_key_exists('external_department_id', $data) ? $data['external_department_id'] : $task->external_department_id,
            'external_contact_id' => array_key_exists('external_contact_id', $data) ? $data['external_contact_id'] : $task->external_contact_id,
            ]);

            $task->fill([
            'external_organization_id' => array_key_exists('external_organization_id', $data) ? $data['external_organization_id'] : $task->external_organization_id,
            'external_department_id' => array_key_exists('external_department_id', $data) ? $data['external_department_id'] : $task->external_department_id,
            'external_contact_id' => array_key_exists('external_contact_id', $data) ? $data['external_contact_id'] : $task->external_contact_id,
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

            $this->customFieldService->sync($task, $data['custom_fields'] ?? []);

            return $task->refresh()->load([
                'project',
                'assignee',
                'creator',
            ]);
        });
    }
}