<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Workflows;

use App\Core\Roles\Models\Role;
use App\Core\Permissions\Models\Permission;
use App\Models\User;
use App\Core\Departments\Models\Department;
use App\Modules\Tasks\Actions\ReviewTaskAction;
use App\Modules\Tasks\Models\Task;
use App\Modules\Workflows\Models\WorkflowDefinition;
use App\Modules\Workflows\Models\WorkflowStep;
use App\Modules\Workflows\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CorePermissionSeeder::class);
    }

    public function test_start_creates_first_task_for_department_manager_and_keeps_later_steps_pending(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $initiator = User::factory()->create(['status' => 'active']);
        $employee = User::factory()->create(['status' => 'active']);

        $department = Department::query()->create([
            'name' => 'واحد فرآیند',
            'code' => 'WF-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);

        $definition = WorkflowDefinition::query()->create([
            'name' => 'فرآیند دو مرحله‌ای',
            'code' => 'WF-'.Str::lower(Str::random(10)),
            'created_by' => $initiator->id,
            'is_active' => true,
        ]);

        $first = WorkflowStep::query()->create([
            'workflow_definition_id' => $definition->id,
            'step_order' => 1,
            'name' => 'بررسی مدیر',
            'task_title' => 'بررسی درخواست',
            'actor_type' => 'department_manager',
            'department_id' => $department->id,
            'review_required' => false,
            'priority' => 'normal',
            'is_active' => true,
        ]);

        WorkflowStep::query()->create([
            'workflow_definition_id' => $definition->id,
            'step_order' => 2,
            'name' => 'انجام کار',
            'task_title' => 'انجام عملیات',
            'actor_type' => 'specific_user',
            'actor_id' => $employee->id,
            'department_id' => $department->id,
            'review_required' => true,
            'priority' => 'high',
            'is_active' => true,
        ]);

        $instance = app(WorkflowService::class)->start($definition, $initiator, null, $department);

        $this->assertSame('running', $instance->status);
        $this->assertSame(2, $instance->steps()->count());

        $activeStep = $instance->steps()->where('status', 'active')->firstOrFail();
        $this->assertSame((int) $first->id, (int) $activeStep->workflow_step_id);
        $this->assertSame((int) $manager->id, (int) $activeStep->task->assignee_id);
        $this->assertSame('pending', $instance->steps()->where('status', 'pending')->firstOrFail()->status);
    }

    public function test_approval_advances_to_next_step_and_final_approval_completes_instance(): void
    {
        $manager = $this->userWithPermission('tasks.review');
        $employee = User::factory()->create(['status' => 'active']);
        $initiator = User::factory()->create(['status' => 'active']);

        $department = Department::query()->create([
            'name' => 'واحد تایید',
            'code' => 'AP-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);

        $definition = WorkflowDefinition::query()->create([
            'name' => 'فرآیند تایید',
            'code' => 'AP-'.Str::lower(Str::random(10)),
            'created_by' => $initiator->id,
            'is_active' => true,
        ]);

        WorkflowStep::query()->create([
            'workflow_definition_id' => $definition->id,
            'step_order' => 1,
            'name' => 'انجام',
            'task_title' => 'انجام کار',
            'actor_type' => 'specific_user',
            'actor_id' => $employee->id,
            'department_id' => $department->id,
            'review_required' => true,
            'priority' => 'normal',
            'is_active' => true,
        ]);

        WorkflowStep::query()->create([
            'workflow_definition_id' => $definition->id,
            'step_order' => 2,
            'name' => 'تایید نهایی',
            'task_title' => 'تایید نهایی',
            'actor_type' => 'department_manager',
            'department_id' => $department->id,
            'review_required' => true,
            'priority' => 'normal',
            'is_active' => true,
        ]);

        $instance = app(WorkflowService::class)->start($definition, $initiator, null, $department);
        $firstTask = $instance->steps()->whereHas('workflowStep', fn ($query) => $query->where('step_order', 1))->firstOrFail()->task;

        $firstTask->update([
            'status' => 'submitted',
            'review_status' => 'pending',
            'review_requested_at' => now(),
        ]);

        $advanced = app(ReviewTaskAction::class)->approve($firstTask, $manager);

        $this->assertSame('completed', $advanced->status);

        $instanceAfterFirstApproval = app(WorkflowService::class)
            ->advanceAfterTaskApproval($firstTask);

        $this->assertNotNull($instanceAfterFirstApproval);
        $nextStep = $instanceAfterFirstApproval->steps()->where('status', 'active')->firstOrFail();
        $this->assertSame((int) $manager->id, (int) $nextStep->task->assignee_id);

        $nextStep->task->update([
            'status' => 'submitted',
            'review_status' => 'pending',
            'review_requested_at' => now(),
        ]);

        $completed = app(ReviewTaskAction::class)->approve($nextStep->task, $manager);

        $this->assertSame('completed', $completed->status);

        $finalInstance = app(WorkflowService::class)
            ->advanceAfterTaskApproval($nextStep->task);

        $this->assertSame('completed', $finalInstance?->status);
        $this->assertNotNull($finalInstance?->completed_at);
    }

    private function userWithPermission(string $permissionCode): User
    {
        $permission = Permission::query()->where('code', $permissionCode)->firstOrFail();

        $role = Role::query()->create([
            'name' => 'Workflow Test Role '.Str::upper(Str::random(8)),
            'code' => 'workflow-'.Str::lower(Str::random(12)),
            'description' => 'Workflow integration test role',
            'is_active' => true,
            'is_system' => false,
        ]);

        $role->permissions()->attach($permission->id, ['scope' => 'organization']);

        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);

        return $user->fresh();
    }
}