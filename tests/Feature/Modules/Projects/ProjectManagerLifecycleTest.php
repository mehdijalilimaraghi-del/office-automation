<?php

namespace Tests\Feature\Modules\Projects;

use App\Core\Departments\Models\Department;
use App\Core\Users\Actions\DeleteUserAction;
use App\Core\Users\Actions\ToggleUserStatusAction;
use App\Models\User;
use App\Modules\Projects\Contracts\ProjectServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProjectManagerLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function addProjectManagementMembership(User $user): void
    {
        $parent = Department::query()->firstOrCreate(
            ['name' => 'مدیریت'],
            ['code' => 'MGMT', 'is_active' => true],
        );

        $department = Department::query()->firstOrCreate(
            ['name' => 'مدیریت پروژه', 'parent_id' => $parent->id],
            ['code' => 'PROJECT-MGMT', 'is_active' => true],
        );

        $department->members()->syncWithoutDetaching([
            $user->id => ['is_primary' => false, 'joined_at' => now()],
        ]);
    }

    public function test_project_manager_cannot_be_deactivated(): void
    {
        $admin = User::factory()->create();
        $manager = User::factory()->create();
        $this->addProjectManagementMembership($manager);

        $this->actingAs($admin);

        app(ProjectServiceContract::class)->create([
            'name' => 'Lifecycle Project',
            'code' => 'PRJ-LIFE-001',
            'manager_id' => $manager->id,
        ], $admin);

        $this->expectException(ValidationException::class);

        app(ToggleUserStatusAction::class)->execute($manager);

        $this->assertTrue($manager->fresh()->isActive());
    }

    public function test_project_manager_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $manager = User::factory()->create();

        $this->addProjectManagementMembership($manager);

        $this->actingAs($admin);

        app(ProjectServiceContract::class)->create([
            'name' => 'Lifecycle Project',
            'code' => 'PRJ-LIFE-002',
            'manager_id' => $manager->id,
        ], $admin);

        $this->expectException(ValidationException::class);

        app(DeleteUserAction::class)->execute($manager);

        $this->assertDatabaseHas('users', [
            'id' => $manager->id,
        ]);
    }
}
