<?php

namespace Tests\Feature\Modules\Projects;

use App\Models\User;
use App\Modules\Tasks\Models\Task;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_project_cascades_project_roles_and_project_members(): void
    {
        $manager = User::factory()->create();
        $member = User::factory()->create();

        $projectId = $this->createProject(
            code: 'PRJ-001',
            managerId: $manager->id,
            createdBy: $manager->id
        );

        $roleId = DB::table('project_roles')->insertGetId([
            'project_id' => $projectId,
            'name' => 'Developer',
            'code' => 'developer',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('project_user')->insert([
            'project_id' => $projectId,
            'user_id' => $manager->id,
            'project_role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('project_user')->insert([
            'project_id' => $projectId,
            'user_id' => $member->id,
            'project_role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $projectId,
        ]);

        $this->assertDatabaseHas('project_roles', [
            'id' => $roleId,
            'project_id' => $projectId,
        ]);

        $this->assertDatabaseCount('project_user', 2);

        DB::table('projects')
            ->where('id', $projectId)
            ->delete();

        $this->assertDatabaseMissing('projects', [
            'id' => $projectId,
        ]);

        $this->assertDatabaseMissing('project_roles', [
            'id' => $roleId,
        ]);

        $this->assertDatabaseCount('project_user', 0);
    }

    public function test_deleting_project_preserves_tasks_by_nulling_project_reference(): void
    {
        $manager = User::factory()->create();
        $projectId = $this->createProject(
            code: 'PRJ-TASK-NULL',
            managerId: $manager->id,
            createdBy: $manager->id
        );

        $taskId = DB::table('tasks')->insertGetId([
            'project_id' => $projectId,
            'title' => 'Task to preserve',
            'status' => 'todo',
            'priority' => 'normal',
            'assignee_id' => $manager->id,
            'created_by' => $manager->id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('projects')->where('id', $projectId)->delete();

        $this->assertDatabaseHas('tasks', [
            'id' => $taskId,
            'project_id' => null,
        ]);
    }

    public function test_deleting_project_role_with_members_is_rejected(): void
    {
        $manager = User::factory()->create();

        $projectId = $this->createProject(
            code: 'PRJ-002',
            managerId: $manager->id,
            createdBy: $manager->id
        );

        $roleId = DB::table('project_roles')->insertGetId([
            'project_id' => $projectId,
            'name' => 'Manager',
            'code' => 'manager',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('project_user')->insert([
            'project_id' => $projectId,
            'user_id' => $manager->id,
            'project_role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('project_roles')
            ->where('id', $roleId)
            ->delete();
    }

    public function test_deleting_user_who_is_project_manager_is_rejected(): void
    {
        $manager = User::factory()->create();
        $creator = User::factory()->create();

        $this->createProject(
            code: 'PRJ-003',
            managerId: $manager->id,
            createdBy: $creator->id
        );

        $this->expectException(QueryException::class);

        DB::table('users')
            ->where('id', $manager->id)
            ->delete();
    }

    public function test_deleting_user_who_created_project_is_rejected(): void
    {
        $manager = User::factory()->create();
        $creator = User::factory()->create();

        $this->createProject(
            code: 'PRJ-004',
            managerId: $manager->id,
            createdBy: $creator->id
        );

        $this->expectException(QueryException::class);

        DB::table('users')
            ->where('id', $creator->id)
            ->delete();
    }

    public function test_deleting_user_cascades_their_project_membership(): void
    {
        $manager = User::factory()->create();
        $member = User::factory()->create();

        $projectId = $this->createProject(
            code: 'PRJ-005',
            managerId: $manager->id,
            createdBy: $manager->id
        );

        $roleId = DB::table('project_roles')->insertGetId([
            'project_id' => $projectId,
            'name' => 'Employee',
            'code' => 'employee',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('project_user')->insert([
            'project_id' => $projectId,
            'user_id' => $member->id,
            'project_role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('project_user', [
            'project_id' => $projectId,
            'user_id' => $member->id,
        ]);

        DB::table('users')
            ->where('id', $member->id)
            ->delete();

        $this->assertDatabaseMissing('project_user', [
            'project_id' => $projectId,
            'user_id' => $member->id,
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $projectId,
        ]);

        $this->assertDatabaseHas('project_roles', [
            'id' => $roleId,
        ]);
    }

    private function createProject(
        string $code,
        int $managerId,
        int $createdBy
    ): int {
        return DB::table('projects')->insertGetId([
            'name' => 'Test Project',
            'code' => $code,
            'status' => 'planning',
            'manager_id' => $managerId,
            'created_by' => $createdBy,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}