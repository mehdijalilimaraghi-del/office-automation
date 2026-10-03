<?php

namespace Tests\Feature\Modules\Projects;

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

    public function test_project_manager_cannot_be_deactivated(): void
    {
        $admin = User::factory()->create();
        $manager = User::factory()->create();

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
