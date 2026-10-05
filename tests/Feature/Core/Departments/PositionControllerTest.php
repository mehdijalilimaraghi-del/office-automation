<?php

namespace Tests\Feature\Core\Departments;

use App\Core\Departments\Models\Department;
use App\Core\Departments\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Core\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class PositionControllerTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithPermissions;

    public function test_user_can_be_assigned_to_position_in_same_department(): void
    {
        $actor = User::factory()->create();
        $member = User::factory()->create();

        $this->grantPermissions($actor, ['departments.manage_positions']);

        $department = Department::create([
            'name' => 'فناوری اطلاعات',
            'code' => 'IT',
            'is_active' => true,
        ]);

        $department->members()->attach($member->id, [
            'is_primary' => true,
            'joined_at' => now(),
        ]);

        $position = Position::create([
            'department_id' => $department->id,
            'name' => 'کارشناس',
            'is_active' => true,
        ]);

        $response = $this->actingAs($actor)->post(
            route('departments.positions.members.store', [
                'department' => $department,
                'position' => $position,
            ]),
            [
                'user_id' => $member->id,
                'is_primary' => true,
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('position_assignments', [
            'position_id' => $position->id,
            'user_id' => $member->id,
            'is_primary' => true,
            'is_active' => true,
        ]);
    }

    public function test_position_from_another_department_returns_not_found(): void
    {
        $actor = User::factory()->create();
        $member = User::factory()->create();

        $this->grantPermissions($actor, ['departments.manage_positions']);

        $firstDepartment = Department::create([
            'name' => 'فناوری اطلاعات',
            'code' => 'IT',
            'is_active' => true,
        ]);

        $secondDepartment = Department::create([
            'name' => 'مالی',
            'code' => 'FIN',
            'is_active' => true,
        ]);

        $firstDepartment->members()->attach($member->id, [
            'is_primary' => true,
            'joined_at' => now(),
        ]);

        $position = Position::create([
            'department_id' => $secondDepartment->id,
            'name' => 'کارشناس مالی',
            'is_active' => true,
        ]);

        $this->actingAs($actor)->post(
            route('departments.positions.members.store', [
                'department' => $firstDepartment,
                'position' => $position,
            ]),
            [
                'user_id' => $member->id,
                'is_primary' => false,
            ]
        )->assertNotFound();
    }
}
