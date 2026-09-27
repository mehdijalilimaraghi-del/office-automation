<?php

namespace Tests\Feature\Core\Roles;

use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_can_have_multiple_permissions(): void
    {
        $role = Role::create([
            'name' => 'مدیر پروژه',
            'code' => 'project_manager',
            'is_active' => true,
            'is_system' => false,
        ]);

        $view = Permission::create([
            'name' => 'مشاهده پروژه‌ها',
            'code' => 'projects.view',
            'is_active' => true,
            'is_system' => false,
        ]);

        $create = Permission::create([
            'name' => 'ایجاد پروژه',
            'code' => 'projects.create',
            'is_active' => true,
            'is_system' => false,
        ]);

        $role->permissions()->attach([
            $view->id,
            $create->id,
        ]);

        $this->assertCount(2, $role->fresh()->permissions);
        $this->assertTrue(
            $role->fresh()->permissions->contains('id', $view->id)
        );
        $this->assertTrue(
            $role->fresh()->permissions->contains('id', $create->id)
        );
    }

    public function test_permission_can_belong_to_multiple_roles(): void
    {
        $roleOne = Role::create([
            'name' => 'مدیر پروژه',
            'code' => 'project_manager',
            'is_active' => true,
            'is_system' => false,
        ]);

        $roleTwo = Role::create([
            'name' => 'کارشناس پروژه',
            'code' => 'project_specialist',
            'is_active' => true,
            'is_system' => false,
        ]);

        $permission = Permission::create([
            'name' => 'مشاهده پروژه‌ها',
            'code' => 'projects.view',
            'is_active' => true,
            'is_system' => false,
        ]);

        $permission->roles()->attach([
            $roleOne->id,
            $roleTwo->id,
        ]);

        $this->assertCount(2, $permission->fresh()->roles);
    }

    public function test_duplicate_role_permission_assignment_is_prevented(): void
    {
        $role = Role::create([
            'name' => 'مدیر پروژه',
            'code' => 'project_manager',
            'is_active' => true,
            'is_system' => false,
        ]);

        $permission = Permission::create([
            'name' => 'مشاهده پروژه‌ها',
            'code' => 'projects.view',
            'is_active' => true,
            'is_system' => false,
        ]);

        $role->permissions()->attach($permission->id);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $role->permissions()->attach($permission->id);
    }
}