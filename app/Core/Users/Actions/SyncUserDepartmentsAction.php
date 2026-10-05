<?php

namespace App\Core\Users\Actions;

use App\Models\User;
use App\Core\Audit\Contracts\AuditServiceInterface;
use Illuminate\Validation\ValidationException;

class SyncUserDepartmentsAction
{
    public function __construct(private readonly AuditServiceInterface $audit)
    {
    }

    public function execute(
        User $user,
        array $departmentIds,
        ?int $primaryDepartmentId = null
    ): void {
        $oldDepartmentIds = $user->departmentMemberships()->pluck('department_id')->map(fn ($id) => (int) $id)->values()->all();

        $departmentIds = array_values(array_unique(array_map('intval', $departmentIds)));

        if ($primaryDepartmentId !== null && ! in_array($primaryDepartmentId, $departmentIds, true)) {
            throw ValidationException::withMessages([
                'primary_department_id' => 'واحد اصلی باید یکی از واحدهای انتخاب‌شده باشد.',
            ]);
        }

        $managedDepartmentIds = $user->managedDepartments()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (array_diff($managedDepartmentIds, $departmentIds) !== []) {
            throw ValidationException::withMessages([
                'department_ids' => 'کاربر مدیر یک یا چند دپارتمان است. ابتدا مدیر دپارتمان را حذف یا تغییر دهید.',
            ]);
        }

        $existing = $user->departmentMemberships()->get()->keyBy('department_id');

        foreach ($departmentIds as $departmentId) {
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

        $query = $user->departmentMemberships();

        if ($departmentIds !== []) {
            $query->whereNotIn('department_id', $departmentIds);
        }

        $query->delete();

        $this->audit->record('user.departments_synced', $user, ['department_ids' => $oldDepartmentIds], ['department_ids' => $departmentIds, 'primary_department_id' => $primaryDepartmentId]);
    }
}
