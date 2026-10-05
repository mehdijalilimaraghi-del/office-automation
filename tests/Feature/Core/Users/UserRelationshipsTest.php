<?php

namespace Tests\Feature\Core\Users;

use App\Core\Departments\Models\Department;
use App\Core\Roles\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_belong_to_multiple_departments(): void
    {
        $departmentOne = Department::create([
            'name' => 'فناوری اطلاعات',
            'code' => 'IT',
            'is_active' => true,
        ]);

        $departmentTwo = Department::create([
            'name' => 'فروش',
            'code' => 'SALES',
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $user->departments()->attach([
            $departmentOne->id => [
                'is_primary' => true,
                'joined_at' => now(),
            ],
            $departmentTwo->id => [
                'is_primary' => false,
                'joined_at' => now(),
            ],
        ]);

        $user->load('departments');

        $this->assertCount(2, $user->departments);
        $this->assertTrue(
            $user->departments->contains(
                fn (Department $department) => $department->is($departmentOne)
            )
        );
        $this->assertTrue(
            $user->departments->contains(
                fn (Department $department) => $department->is($departmentTwo)
            )
        );
        $this->assertTrue(
            (bool) $user->departments
                ->firstWhere('id', $departmentOne->id)
                ->pivot->is_primary
        );
    }

    public function test_user_can_have_multiple_roles(): void
    {
        $user = User::factory()->create();

        $roleOne = Role::create([
            'name' => 'مدیر پروژه',
            'code' => 'PROJECT_MANAGER',
            'is_active' => true,
            'is_system' => false,
        ]);

        $roleTwo = Role::create([
            'name' => 'کارشناس',
            'code' => 'EXPERT',
            'is_active' => true,
            'is_system' => false,
        ]);

        $user->roles()->attach([
            $roleOne->id,
            $roleTwo->id,
        ]);

        $user->load('roles');

        $this->assertCount(2, $user->roles);

        $this->assertTrue(
            $user->roles->contains(
                fn (Role $role) => $role->is($roleOne)
            )
        );

        $this->assertTrue(
            $user->roles->contains(
                fn (Role $role) => $role->is($roleTwo)
            )
        );
    }

    public function test_role_can_belong_to_multiple_users(): void
    {
        $role = Role::create([
            'name' => 'کارشناس',
            'code' => 'EXPERT',
            'is_active' => true,
            'is_system' => false,
        ]);

        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();

        $role->users()->attach([
            $userOne->id,
            $userTwo->id,
        ]);

        $role->load('users');

        $this->assertCount(2, $role->users);

        $this->assertTrue(
            $role->users->contains(
                fn (User $user) => $user->is($userOne)
            )
        );

        $this->assertTrue(
            $role->users->contains(
                fn (User $user) => $user->is($userTwo)
            )
        );
    }

    public function test_duplicate_role_assignment_is_prevented(): void
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'کارشناس',
            'code' => 'EXPERT',
            'is_active' => true,
            'is_system' => false,
        ]);

        $user->roles()->attach($role->id);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $user->roles()->attach($role->id);
    }

    public function test_operational_scope_excludes_active_system_admin_accounts(): void
    {
        $systemAdminRole = Role::create([
            'name' => 'مدیر سیستم',
            'code' => 'SYSTEM_ADMIN',
            'is_active' => true,
            'is_system' => true,
        ]);

        $systemAdmin = User::factory()->create([
            'name' => 'مدیر اصلی سیستم',
            'status' => 'active',
        ]);
        $systemAdmin->roles()->attach($systemAdminRole->id);

        $operationalUser = User::factory()->create([
            'name' => 'کاربر عملیاتی',
            'status' => 'active',
        ]);

        $operationalUsers = User::query()
            ->operational()
            ->where('status', 'active')
            ->get();

        $this->assertTrue($operationalUsers->contains($operationalUser));
        $this->assertFalse($operationalUsers->contains($systemAdmin));
    }

    public function test_user_status_helpers_work(): void
    {
        $activeUser = User::factory()->create([
            'status' => 'active',
        ]);

        $inactiveUser = User::factory()
            ->inactive()
            ->create();

        $this->assertTrue($activeUser->isActive());
        $this->assertFalse($activeUser->isInactive());

        $this->assertTrue($inactiveUser->isInactive());
        $this->assertFalse($inactiveUser->isActive());
    }
}