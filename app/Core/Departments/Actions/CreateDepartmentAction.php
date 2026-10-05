<?php

namespace App\Core\Departments\Actions;

use App\Core\Departments\Models\Department;
use App\Modules\Projects\Actions\CreateDepartmentDefaultProjectAction;
use Illuminate\Support\Facades\DB;

class CreateDepartmentAction
{
    public function __construct(
        private readonly CreateDepartmentDefaultProjectAction $createDefaultProjectAction,
    ) {
    }

    public function execute(array $data): Department
    {
        $managerId = isset($data['manager_id'])
            ? (int) $data['manager_id']
            : null;

        return DB::transaction(function () use ($data, $managerId): Department {
            $department = Department::create([
                'parent_id' => $data['parent_id'] ?? null,
                'manager_id' => null,
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'icon' => $data['icon'] ?? 'building-2',
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if ($managerId !== null) {
                $this->ensureManagerMembership($department, $managerId);

                $department->update([
                    'manager_id' => $managerId,
                ]);
            }

            $department = $department->refresh();

            $this->createDefaultProjectAction->execute($department);

            return $department->refresh();
        });
    }

    private function ensureManagerMembership(
        Department $department,
        int $managerId
    ): void {
        if (! $department->members()->whereKey($managerId)->exists()) {
            $department->members()->attach($managerId, [
                'is_primary' => false,
                'joined_at' => now(),
            ]);
        }
    }
}
