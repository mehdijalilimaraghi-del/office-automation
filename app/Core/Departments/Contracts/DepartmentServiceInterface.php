<?php

namespace App\Core\Departments\Contracts;

use App\Core\Departments\Models\Department;
use Illuminate\Database\Eloquent\Collection;

interface DepartmentServiceInterface
{
    public function create(array $data): Department;

    public function update(Department $department, array $data): Department;

    public function delete(Department $department): void;

    public function toggleStatus(Department $department): Department;

    public function list(): Collection;
}