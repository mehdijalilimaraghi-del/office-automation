<?php

namespace Tests\Feature\Core\Departments;

use App\Core\Departments\Actions\AssignPositionAction;
use App\Core\Departments\Actions\CreatePositionAction;
use App\Core\Departments\Actions\UnassignPositionAction;
use App\Core\Departments\Models\Department;
use App\Core\Departments\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_position_is_created_under_department_and_can_be_assigned_to_member(): void
    {
        $department = Department::query()->create([
            'name' => 'حسابداری',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['status' => 'active']);
        $department->members()->attach($user->id, ['is_primary' => true, 'joined_at' => now()]);

        $position = app(CreatePositionAction::class)->execute($department, [
            'name' => 'کارشناس حسابداری',
            'is_active' => true,
        ]);

        $assignment = app(AssignPositionAction::class)->execute($position, $user, true);

        $this->assertDatabaseHas('positions', [
            'id' => $position->id,
            'department_id' => $department->id,
            'name' => 'کارشناس حسابداری',
        ]);
        $this->assertDatabaseHas('position_assignments', [
            'position_id' => $position->id,
            'user_id' => $user->id,
            'is_primary' => 1,
        ]);
        $this->assertSame($user->id, $assignment->user_id);
    }

    public function test_position_cannot_be_assigned_to_user_outside_department(): void
    {
        $department = Department::query()->create(['name' => 'مالی', 'is_active' => true]);
        $user = User::factory()->create(['status' => 'active']);
        $position = app(CreatePositionAction::class)->execute($department, [
            'name' => 'مدیر مالی',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(AssignPositionAction::class)->execute($position, $user);
    }

    public function test_primary_position_is_unique_for_user(): void
    {
        $department = Department::query()->create(['name' => 'عملیات', 'is_active' => true]);
        $user = User::factory()->create(['status' => 'active']);
        $department->members()->attach($user->id, ['is_primary' => true, 'joined_at' => now()]);

        $first = app(CreatePositionAction::class)->execute($department, ['name' => 'کارشناس عملیات', 'is_active' => true]);
        $second = app(CreatePositionAction::class)->execute($department, ['name' => 'سرپرست عملیات', 'is_active' => true]);

        app(AssignPositionAction::class)->execute($first, $user, true);
        app(AssignPositionAction::class)->execute($second, $user, true);

        $this->assertDatabaseHas('position_assignments', [
            'id' => $second->assignments()->firstOrFail()->id,
            'is_primary' => 1,
        ]);
        $this->assertDatabaseHas('position_assignments', [
            'id' => $first->assignments()->firstOrFail()->id,
            'is_primary' => 0,
        ]);
    }

    public function test_unassigning_primary_position_requires_replacement_first(): void
    {
        $department = Department::query()->create(['name' => 'منابع انسانی', 'is_active' => true]);
        $user = User::factory()->create(['status' => 'active']);
        $department->members()->attach($user->id, ['is_primary' => true, 'joined_at' => now()]);
        $position = app(CreatePositionAction::class)->execute($department, ['name' => 'مدیر منابع انسانی', 'is_active' => true]);
        app(AssignPositionAction::class)->execute($position, $user, true);

        $this->expectException(ValidationException::class);

        app(UnassignPositionAction::class)->execute($position, $user);
    }
}
