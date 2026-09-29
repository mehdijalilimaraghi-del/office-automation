<?php

namespace App\Core\Users\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class SyncUserDepartmentsAction
{
    public function execute(
        User $user,
        array $departmentIds,
        ?int $primaryDepartmentId = null
    ): void {
        $departmentIds = array_values(
            array_unique(
                array_map('intval', $departmentIds)
            )
        );

        if (
            $primaryDepartmentId !== null &&
            ! in_array($primaryDepartmentId, $departmentIds, true)
        ) {
            throw ValidationException::withMessages([
                'primary_department_id' => 'واحد اصلی باید یکی از واحدهای انتخاب‌شده باشد.',
            ]);
        }

        $existing = $user->departmentMemberships()
            ->get()
            ->keyBy('department_id');

        $keepIds = [];

        foreach ($departmentIds as $departmentId) {
            $keepIds[] = $departmentId;

            if ($existing->has($departmentId)) {
                $existing->get($departmentId)->update([
                    'is_primary' => $primaryDepartmentId === $departmentId,
                ]);

                continue;
            }

            $user->departmentMemberships()->create([
                'department_id' => $departmentId,
                'is_primary' => $primaryDepartmentId === $departmentId,
                'joined_at' => now(),
            ]);
        }

        $user->departmentMemberships()
            ->when(
                $keepIds !== [],
                fn ($query) => $query->whereNotIn('department_id', $keepIds)
            )
            ->when(
                $keepIds === [],
                fn ($query) => $query
            )
            ->delete();
    }
}
