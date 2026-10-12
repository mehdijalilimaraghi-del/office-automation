<?php

namespace App\Modules\Projects\Actions;

use App\Models\User;
use App\Core\Departments\Models\Department;
use App\Core\Settings\Services\OrganizationDepartmentRoleService;
use App\Core\CustomFields\Services\CustomFieldService;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectSection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateProjectAction
{
    public function __construct(
        private readonly CustomFieldService $customFieldService,
    ) {
    }

    public function execute(
        array $data,
        User $creator,
    ): Project {
        return DB::transaction(
            function () use ($data, $creator): Project {
                $manager = User::query()->find(
                    $data['manager_id']
                );

                if ($manager === null) {
                    throw new InvalidArgumentException(
                        'مدیر پروژه یافت نشد.'
                    );
                }

                if (! $manager->isActive()) {
                    throw new InvalidArgumentException(
                        'مدیر پروژه باید کاربر فعال باشد.'
                    );
                }

                if (! $manager->isSystemAdmin()) {
                    $isDepartmentMemberOrManager = app(OrganizationDepartmentRoleService::class)
                        ->userBelongsTo($manager, OrganizationDepartmentRoleService::PROJECT_MANAGEMENT);

                    if (! $isDepartmentMemberOrManager) {
                        throw new InvalidArgumentException(
                            'مدیر پروژه باید عضو یا مدیر واحد تعیین‌شده برای مدیریت پروژه‌ها باشد.'
                        );
                    }
                }

                if (
                    isset($data['start_date']) &&
                    isset($data['end_date']) &&
                    $data['start_date'] !== null &&
                    $data['end_date'] !== null &&
                    $data['end_date'] < $data['start_date']
                ) {
                    throw new InvalidArgumentException(
                        'تاریخ پایان پروژه نمی‌تواند قبل از تاریخ شروع باشد.'
                    );
                }

                $project = Project::query()->create([
                    'department_id' => $data['department_id'] ?? null,
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'status' => $data['status'] ?? 'planning',
                    'start_date' => $data['start_date'] ?? null,
                    'end_date' => $data['end_date'] ?? null,
                    'manager_id' => $manager->id,
                    'created_by' => $creator->id,
                    'is_active' => $data['is_active'] ?? true,
                ]);

                $this->customFieldService->sync($project, $data['custom_fields'] ?? []);

                foreach (array_values($data['sections'] ?? []) as $index => $sectionData) {
                    $department = Department::query()
                        ->with('manager')
                        ->whereKey($sectionData['department_id'])
                        ->where('is_active', true)
                        ->first();

                    if ($department === null || $department->manager === null || ! $department->manager->isActive()) {
                        throw new InvalidArgumentException('واحد مسئول هر بخش باید فعال و دارای مدیر فعال باشد.');
                    }

                    ProjectSection::query()->create([
                        'project_id' => $project->id,
                        'department_id' => $department->id,
                        'name' => $sectionData['name'],
                        'description' => $sectionData['description'] ?? null,
                        'manager_can_create_tasks' => (bool) ($sectionData['manager_can_create_tasks'] ?? true),
                        'sort_order' => $index,
                    ]);
                }

                return $project->load([
                    'manager',
                    'creator',
                    'department',
                ]);
            }
        );
    }
}