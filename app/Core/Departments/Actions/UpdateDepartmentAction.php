<?php

namespace App\Core\Departments\Actions;

use App\Core\Departments\Models\Department;
use App\Core\CustomFields\Services\CustomFieldService;
use App\Modules\Projects\Actions\EnsureDepartmentProjectSectionAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateDepartmentAction
{
    public function __construct(
        private readonly CustomFieldService $customFieldService,
        private readonly EnsureDepartmentProjectSectionAction $ensureDepartmentProjectSectionAction,
    ) {
    }

    public function execute(Department $department, array $data): Department
    {
        $parentId = $data['parent_id'] ?? null;
        $managerId = isset($data['manager_id'])
            ? (int) $data['manager_id']
            : null;

        if (
            $department->is_system_default
            && (int) ($parentId ?? 0) !== (int) ($department->parent_id ?? 0)
        ) {
            throw ValidationException::withMessages([
                'parent_id' => 'جایگاه واحدهای پیش‌فرض در ساختار سازمانی قابل تغییر نیست.',
            ]);
        }

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
                'code' => array_key_exists('code', $data) ? (filled($data['code']) ? strtoupper(trim((string) $data['code'])) : null) : $department->code,
                'icon' => $data['icon'] ?? 'building-2',
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'manager_signature_path' => $data['manager_signature_path'] ?? $department->manager_signature_path,
                'manager_signature_active' => $data['manager_signature_active'] ?? false,
            ]);

            $this->customFieldService->sync($department, $data['custom_fields'] ?? []);
            $this->ensureDepartmentProjectSectionAction->execute($department->refresh());

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
