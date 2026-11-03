<?php

declare(strict_types=1);

namespace App\Modules\Projects\Actions;

use App\Modules\Projects\Models\Project;
use App\Core\CustomFields\Services\CustomFieldService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateProjectAction
{
    public function __construct(
        private readonly CustomFieldService $customFieldService,
    ) {
    }

    public function execute(
        Project $project,
        array $data
    ): Project {
        if (
            isset($data['manager_id'])
            && (int) $data['manager_id'] !== (int) $project->manager_id
        ) {
            throw ValidationException::withMessages([
                'manager_id' => 'مدیر پروژه از این مسیر قابل تغییر نیست.',
            ]);
        }

        if (
            isset($data['start_date'], $data['end_date'])
            && $data['start_date'] !== null
            && $data['end_date'] !== null
            && $data['start_date'] > $data['end_date']
        ) {
            throw ValidationException::withMessages([
                'end_date' => 'تاریخ پایان پروژه نمی‌تواند قبل از تاریخ شروع باشد.',
            ]);
        }

        if ($project->is_system_default && array_key_exists('department_id', $data) && $data['department_id'] !== null) {
            throw ValidationException::withMessages([
                'department_id' => 'پروژه عمومی سازمان به واحد مشخصی متصل نمی‌شود.',
            ]);
        }

        return DB::transaction(function () use ($project, $data): Project {
            $project->update([
                'department_id' => $project->is_system_default ? null : ($data['department_id'] ?? $project->department_id),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'is_active' => $data['is_active'] ?? $project->is_active,
            ]);

            $this->customFieldService->sync($project, $data['custom_fields'] ?? []);

            return $project->refresh();
        });
    }
}