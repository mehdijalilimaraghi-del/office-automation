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

    public function test_user_belongs_to_department(): void
    {
        $department = Department::create([
            'name' => 'فناوری اطلاعات',
            'code' => 'IT',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'department_id' => $department->id,
        ]);

        $this->assertTrue(
            $user->department->is($department)
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