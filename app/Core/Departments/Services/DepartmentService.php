<?php

namespace App\Core\Departments\Services;

use App\Core\Departments\Actions\CreateDepartmentAction;
use App\Core\Departments\Actions\DeleteDepartmentAction;
use App\Core\Departments\Actions\ToggleDepartmentStatusAction;
use App\Core\Departments\Actions\UpdateDepartmentAction;
use App\Core\Departments\Contracts\DepartmentServiceInterface;
use App\Core\Departments\Models\Department;
use Illuminate\Database\Eloquent\Collection;

class DepartmentService implements DepartmentServiceInterface
{
    public function __construct(
        private readonly CreateDepartmentAction $createDepartmentAction,
        private readonly UpdateDepartmentAction $updateDepartmentAction,
        private readonly DeleteDepartmentAction $deleteDepartmentAction,
        private readonly ToggleDepartmentStatusAction $toggleDepartmentStatusAction,
    ) {
    }

    public function create(array $data): Department
    {
        return $this->createDepartmentAction->execute($data);
    }

    public function update(
        Department $department,
        array $data
    ): Department {
        return $this->updateDepartmentAction->execute(
            $department,
            $data
        );
    }

    public function delete(Department $department): void
    {
        $this->deleteDepartmentAction->execute($department);
    }

    public function toggleStatus(Department $department): Department
    {
        return $this->toggleDepartmentStatusAction->execute(
            $department
        );
    }

    public function list(): Collection
    {
        return Department::query()
            ->with([
                'parent',
                'manager:id,name',
                'members:id,name',
                'positions.users:id,name',
            ])
            ->withCount('children')
            ->orderBy('name')
            ->get();
    }
}
