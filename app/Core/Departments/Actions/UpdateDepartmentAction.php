<?php

namespace App\Core\Departments\Actions;

use App\Core\Departments\Models\Department;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateDepartmentAction
{
    public function execute(Department $department, array $data): Department
    {
        $parentId = $data['parent_id'] ?? null;
        $managerId = isset($data['manager_id'])
            ? (int) $data['manager_id']
            : null;

        if ($parentId !== null) {
            if ((int) $parentId === $department->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'یک دپارتمان نمی‌تواند والد خودش باشد.',
                ]);
            }

            $descendantIds = $department->descendantIds();

            if (in_array((int) $parentId, $descendantIds, true)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'یک دپارتمان نمی‌تواند زیرمجموعه یکی از فرزندان خودش باشد.',
                ]);
            }
        }

        return DB::transaction(function () use (
            $department,
            $data,
            $parentId,
            $managerId
        ): Department {
            if ($managerId !== null) {
                $this->ensureManagerMembership($department, $managerId);
            }

            $department->update([
                'parent_id' => $parentId,
                'manager_id' => $managerId,
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

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
