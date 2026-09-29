<?php

namespace Tests\Feature\Modules\Projects;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_table_has_expected_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns('projects', [
                'id',
                'name',
                'code',
                'description',
                'status',
                'start_date',
                'end_date',
                'manager_id',
                'created_by',
                'is_active',
                'created_at',
                'updated_at',
            ])
        );
    }

    public function test_project_roles_table_has_expected_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns('project_roles', [
                'id',
                'project_id',
                'name',
                'code',
                'description',
                'is_active',
                'created_at',
                'updated_at',
            ])
        );
    }

    public function test_project_user_table_has_expected_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns('project_user', [
                'id',
                'project_id',
                'user_id',
                'project_role_id',
                'created_at',
                'updated_at',
            ])
        );
    }

    public function test_project_code_must_be_unique(): void
    {
        $user = User::factory()->create();

        $this->createProject(
            code: 'PRJ-001',
            userId: $user->id
        );

        $this->expectException(QueryException::class);

        $this->createProject(
            code: 'PRJ-001',
            userId: $user->id
        );
    }

    public function test_project_role_code_must_be_unique_within_project(): void
    {
        $user = User::factory()->create();

        $projectId = $this->createProject(
            code: 'PRJ-001',
            userId: $user->id
        );

        DB::table('project_roles')->insert([
            'project_id' => $projectId,
            'name' => 'Developer',
            'code' => 'developer',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('project_roles')->insert([
            'project_id' => $projectId,
            'name' => 'Another Developer',
            'code' => 'developer',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createProject(
        string $code,
        int $userId
    ): int {
        return DB::table('projects')->insertGetId([
            'name' => 'Test Project',
            'code' => $code,
            'status' => 'planning',
            'manager_id' => $userId,
            'created_by' => $userId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}