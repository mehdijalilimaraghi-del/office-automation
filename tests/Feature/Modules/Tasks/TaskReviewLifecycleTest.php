<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tasks;

use App\Core\Departments\Models\Department;
use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use App\Modules\Tasks\Actions\ReviewTaskAction;
use App\Modules\Tasks\Actions\SubmitTaskAction;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskReviewLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CorePermissionSeeder::class);
    }

    public function test_assignee_without_submit_permission_can_submit_task_for_review(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $assignee = User::factory()->create(['status' => 'active']);

        $department = Department::query()->create([
            'name' => 'Review Unit',
            'code' => 'REV-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);

        $task = Task::query()->create([
            'department_id' => $department->id,
            'title' => 'Review task',
            'status' => 'in_progress',
            'priority' => 'normal',
            'review_required' => false,
            'review_status' => 'not_required',
            'assignee_id' => $assignee->id,
            'created_by' => $manager->id,
        ]);

        $result = app(SubmitTaskAction::class)->execute($task, $assignee);

        $this->assertSame('in_progress', $result->status);
        $this->assertSame('manager_review', $result->sub_status);
        $this->assertSame('pending', $result->review_status);
        $this->assertTrue($result->review_required);
        $this->assertNotNull($result->review_requested_at);
        $this->assertDatabaseHas('task_status_histories', [
            'task_id' => $task->id,
            'from_status' => 'in_progress',
            'to_status' => 'in_progress',
            'changed_by' => $assignee->id,
        ]);
    }

    public function test_department_manager_can_approve_submitted_task(): void
    {
        $manager = $this->userWithPermission('tasks.review');
        $assignee = User::factory()->create(['status' => 'active']);

        $department = Department::query()->create([
            'name' => 'Approval Unit',
            'code' => 'APR-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);

        $task = Task::query()->create([
            'department_id' => $department->id,
            'title' => 'Approval task',
            'status' => 'in_progress',
            'sub_status' => 'manager_review',
            'priority' => 'normal',
            'review_required' => true,
            'review_status' => 'pending',
            'assignee_id' => $assignee->id,
            'created_by' => $manager->id,
            'review_requested_at' => now(),
        ]);

        $result = app(ReviewTaskAction::class)->approve($task, $manager);

        $this->assertSame('completed', $result->status);
        $this->assertSame('approved', $result->review_status);
        $this->assertSame($manager->id, $result->reviewed_by);
        $this->assertNotNull($result->completed_at);
        $this->assertDatabaseHas('task_status_histories', [
            'task_id' => $task->id,
            'from_status' => 'in_progress',
            'to_status' => 'completed',
            'changed_by' => $manager->id,
        ]);
    }

    public function test_department_manager_can_return_task_with_reason(): void
    {
        $manager = $this->userWithPermission('tasks.review');
        $assignee = User::factory()->create(['status' => 'active']);

        $department = Department::query()->create([
            'name' => 'Return Unit',
            'code' => 'RET-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);

        $task = Task::query()->create([
            'department_id' => $department->id,
            'title' => 'Return task',
            'status' => 'in_progress',
            'sub_status' => 'manager_review',
            'priority' => 'normal',
            'review_required' => true,
            'review_status' => 'pending',
            'assignee_id' => $assignee->id,
            'created_by' => $manager->id,
            'review_requested_at' => now(),
        ]);

        $result = app(ReviewTaskAction::class)->returnTask(
            $task,
            $manager,
            'مدرک نهایی باید اصلاح شود.',
        );

        $this->assertSame('in_progress', $result->status);
        $this->assertSame('rejected', $result->review_status);
        $this->assertSame('مدرک نهایی باید اصلاح شود.', $result->review_note);
        $this->assertSame($manager->id, $result->reviewed_by);
    }

    public function test_non_manager_cannot_review_task(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $reviewer = $this->userWithPermission('tasks.review');
        $assignee = User::factory()->create(['status' => 'active']);

        $department = Department::query()->create([
            'name' => 'Protected Review Unit',
            'code' => 'PRV-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);

        $task = Task::query()->create([
            'department_id' => $department->id,
            'title' => 'Protected review task',
            'status' => 'in_progress',
            'sub_status' => 'manager_review',
            'priority' => 'normal',
            'review_required' => true,
            'review_status' => 'pending',
            'assignee_id' => $assignee->id,
            'created_by' => $manager->id,
            'review_requested_at' => now(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(ReviewTaskAction::class)->approve($task, $reviewer);
    }

    public function test_direct_status_change_cannot_submit_task_for_review(): void
    {
        $actor = User::factory()->create(['status' => 'active']);

        $task = Task::query()->create([
            'title' => 'Protected submit task',
            'status' => 'in_progress',
            'priority' => 'normal',
            'review_required' => true,
            'review_status' => 'not_started',
            'assignee_id' => $actor->id,
            'created_by' => $actor->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(\App\Modules\Tasks\Actions\ChangeTaskStatusAction::class)
            ->execute($task, 'submitted', $actor);
    }

    public function test_pending_review_task_cannot_be_changed_manually(): void
    {
        $actor = User::factory()->create(['status' => 'active']);

        $task = Task::query()->create([
            'title' => 'Pending review task',
            'status' => 'in_progress',
            'sub_status' => 'manager_review',
            'priority' => 'normal',
            'review_required' => true,
            'review_status' => 'pending',
            'review_requested_at' => now(),
            'assignee_id' => $actor->id,
            'created_by' => $actor->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(\App\Modules\Tasks\Actions\ChangeTaskStatusAction::class)
            ->execute($task, 'in_progress', $actor);
    }

    public function test_completed_task_cannot_be_reopened_through_status_change(): void
    {
        $actor = User::factory()->create(['status' => 'active']);

        $task = Task::query()->create([
            'title' => 'Completed task',
            'status' => 'completed',
            'priority' => 'normal',
            'review_required' => false,
            'review_status' => 'not_required',
            'assignee_id' => $actor->id,
            'created_by' => $actor->id,
            'completed_at' => now(),
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(\App\Modules\Tasks\Actions\ChangeTaskStatusAction::class)
            ->execute($task, 'in_progress', $actor);
    }

    public function test_finalized_tasks_reject_direct_status_changes(): void
    {
        $actor = User::factory()->create(['status' => 'active']);

        foreach (['cancelled', 'cannot_continue', 'completed'] as $finalStatus) {
            $task = Task::query()->create([
                'title' => 'Final task '.$finalStatus,
                'status' => $finalStatus,
                'priority' => 'normal',
                'review_required' => false,
                'review_status' => $finalStatus === 'completed' ? 'approved' : 'not_required',
                'assignee_id' => $actor->id,
                'created_by' => $actor->id,
                'completed_at' => $finalStatus === 'completed' ? now() : null,
            ]);

            try {
                app(\\App\\Modules\\Tasks\\Actions\\ChangeTaskStatusAction::class)->execute($task, 'in_progress', $actor);
                $this->fail('Finalized task accepted a status change.');
            } catch (\\InvalidArgumentException $exception) {
                $this->assertStringContainsString('نهایی‌شده', $exception->getMessage());
            }
        }
    }

    private function userWithPermission(string $permissionCode): User
    {
        $permission = Permission::query()->where('code', $permissionCode)->firstOrFail();

        $role = Role::query()->create([
            'name' => 'Review Test Role '.Str::upper(Str::random(8)),
            'code' => 'review-'.Str::lower(Str::random(12)),
            'description' => 'Review lifecycle test role',
            'is_active' => true,
            'is_system' => false,
        ]);

        $role->permissions()->attach($permission->id, ['scope' => 'organization']);

        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);

        return $user->fresh();
    }
}

