<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Cartable;

use App\Core\Departments\Models\Department;
use App\Models\User;
use App\Modules\Cartable\Services\CartableService;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CartableServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cartable_returns_my_tasks_pending_approvals_and_active_referrals(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $employee = User::factory()->create(['status' => 'active']);
        $creator = User::factory()->create(['status' => 'active']);

        $department = Department::query()->create([
            'name' => 'واحد کارتابل',
            'code' => 'CB-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);

        $myTask = Task::query()->create([
            'title' => 'کار من',
            'assignee_id' => $employee->id,
            'created_by' => $creator->id,
            'department_id' => $department->id,
            'status' => 'in_progress',
            'is_active' => true,
        ]);

        $completedTask = Task::query()->create([
            'title' => 'کار تمام‌شده',
            'assignee_id' => $employee->id,
            'created_by' => $creator->id,
            'department_id' => $department->id,
            'status' => 'completed',
            'is_active' => true,
        ]);

        $approvalTask = Task::query()->create([
            'title' => 'در انتظار تایید',
            'assignee_id' => $employee->id,
            'created_by' => $creator->id,
            'department_id' => $department->id,
            'status' => 'submitted',
            'review_required' => true,
            'review_status' => 'pending',
            'review_requested_at' => now(),
            'is_active' => true,
        ]);

        $referredTask = Task::query()->create([
            'title' => 'ارجاع شده',
            'assignee_id' => $employee->id,
            'created_by' => $creator->id,
            'department_id' => $department->id,
            'status' => 'todo',
            'is_active' => true,
        ]);

        TaskAssignment::query()->create([
            'task_id' => $referredTask->id,
            'from_user_id' => $creator->id,
            'to_user_id' => $employee->id,
            'assigned_by' => $creator->id,
            'type' => 'handoff',
            'note' => 'برای بررسی',
            'started_at' => now(),
        ]);

        $cartable = app(CartableService::class)->for($employee);

        $this->assertSame(3, $cartable['summary']['my_tasks']);
        $this->assertSame(0, $cartable['summary']['pending_approvals']);
        $this->assertSame(1, $cartable['summary']['referrals']);
        $this->assertSame($myTask->id, $cartable['myTasks']->first()->id);
        $this->assertSame($referredTask->id, $cartable['referrals']->first()->task_id);

        $managerCartable = app(CartableService::class)->for($manager);

        $this->assertSame(1, $managerCartable['summary']['pending_approvals']);
        $this->assertSame($approvalTask->id, $managerCartable['pendingApprovals']->first()->id);
        $this->assertNotContains($completedTask->id, $managerCartable['myTasks']->pluck('id')->all());
    }
}
