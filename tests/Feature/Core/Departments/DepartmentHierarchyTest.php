<?php

namespace Tests\Feature\Core\Departments;

use App\Core\Departments\Actions\UpdateDepartmentAction;
use App\Core\Departments\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DepartmentHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_cannot_become_child_of_itself(): void
    {
        $department = Department::create([
            'name' => 'مدیریت',
        ]);

        $this->expectException(ValidationException::class);

        app(UpdateDepartmentAction::class)->execute(
            $department,
            [
                'parent_id' => $department->id,
                'name' => 'مدیریت',
                'is_active' => true,
            ]
        );
    }

    public function test_department_cannot_become_child_of_its_descendant(): void
    {
        $parent = Department::create([
            'name' => 'مدیریت',
        ]);

        $child = Department::create([
            'parent_id' => $parent->id,
            'name' => 'واحد فنی',
        ]);

        $grandchild = Department::create([
            'parent_id' => $child->id,
            'name' => 'توسعه نرم‌افزار',
        ]);

        $this->expectException(ValidationException::class);

        app(UpdateDepartmentAction::class)->execute(
            $parent,
            [
                'parent_id' => $grandchild->id,
                'name' => 'مدیریت',
                'is_active' => true,
            ]
        );
    }
}