<?php

declare(strict_types=1);

namespace App\Modules\Workflows\Services;

use App\Models\User;
use App\Core\Departments\Models\Department;
use App\Modules\Projects\Models\Project;
use App\Modules\Tasks\Actions\CreateTaskAction;
use App\Modules\Tasks\Models\Task;
use App\Modules\Workflows\Models\WorkflowDefinition;
use App\Modules\Workflows\Models\WorkflowInstance;
use App\Modules\Workflows\Models\WorkflowInstanceStep;
use App\Modules\Workflows\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkflowService
{
    public function __construct(
        private readonly CreateTaskAction $createTask,
    ) {}

    public function start(
        WorkflowDefinition $definition,
        User $initiator,
        ?Project $project = null,
        ?Department $department = null,
    ): WorkflowInstance {
        if (! $definition->is_active) {
            throw new InvalidArgumentException('فرآیند انتخاب‌شده غیرفعال است.');
        }

        $steps = $definition->steps()
            ->where('is_active', true)
            ->orderBy('step_order')
            ->get();

        if ($steps->isEmpty()) {
            throw new InvalidArgumentException('فرآیند حداقل باید یک مرحله فعال داشته باشد.');
        }

        return DB::transaction(function () use ($definition, $initiator, $project, $department, $steps): WorkflowInstance {
            $instance = WorkflowInstance::query()->create([
                'workflow_definition_id' => $definition->id,
                'initiated_by' => $initiator->id,
                'project_id' => $project?->id,
                'department_id' => $department?->id,
                'status' => 'running',
                'started_at' => now(),
            ]);

            foreach ($steps as $step) {
                $instance->steps()->create([
                    'workflow_step_id' => $step->id,
                    'status' => 'pending',
                ]);
            }

            $this->activateStep($instance->refresh(), $steps->first());

            return $instance->refresh()->load(['definition', 'steps.workflowStep', 'steps.task']);
        });
    }

    public function advanceAfterTaskApproval(Task $task): ?WorkflowInstance
    {
        $instanceStep = WorkflowInstanceStep::query()
            ->where('task_id', $task->id)
            ->where('status', 'active')
            ->with(['instance.definition', 'workflowStep'])
            ->first();

        if ($instanceStep === null) {
            return null;
        }

        return DB::transaction(function () use ($instanceStep): WorkflowInstance {
            $instanceStep->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $instance = $instanceStep->instance->refresh();

            $nextStep = $instance->definition->steps()
                ->where('is_active', true)
                ->where('step_order', '>', $instanceStep->workflowStep->step_order)
                ->orderBy('step_order')
                ->first();

            if ($nextStep === null) {
                $instance->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                return $instance->refresh()->load(['definition', 'steps.workflowStep', 'steps.task']);
            }

            $this->activateStep($instance, $nextStep);

            return $instance->refresh()->load(['definition', 'steps.workflowStep', 'steps.task']);
        });
    }

    private function activateStep(WorkflowInstance $instance, WorkflowStep $step): void
    {
        $instanceStep = $instance->steps()
            ->where('workflow_step_id', $step->id)
            ->firstOrFail();

        $assignee = $this->resolveActor($instance, $step);

        $task = $this->createTask->execute([
            'project_id' => $instance->project_id,
            'department_id' => $step->department_id ?? $instance->department_id,
            'title' => $step->task_title,
            'description' => $step->task_description,
            'status' => 'todo',
            'priority' => $step->priority,
            'review_required' => $step->review_required,
            'assignee_id' => $assignee->id,
        ], $instance->initiator()->firstOrFail());

        $instanceStep->update([
            'task_id' => $task->id,
            'status' => 'active',
            'started_at' => now(),
        ]);
    }

    private function resolveActor(WorkflowInstance $instance, WorkflowStep $step): User
    {
        return match ($step->actor_type) {
            'workflow_initiator' => $instance->initiator()->firstOrFail(),
            'specific_user' => User::query()->findOrFail($step->actor_id),
            'department_manager' => $this->resolveDepartmentManager($instance, $step),
            'project_manager' => $this->resolveProjectManager($instance),
            default => throw new InvalidArgumentException('نوع مسئول مرحله فرآیند پشتیبانی نمی‌شود.'),
        };
    }

    private function resolveDepartmentManager(WorkflowInstance $instance, WorkflowStep $step): User
    {
        $departmentId = $step->department_id ?? $instance->department_id;

        if ($departmentId === null) {
            throw new InvalidArgumentException('برای مرحله مدیر واحد، واحد سازمانی مشخص نشده است.');
        }

        $managerId = Department::query()
            ->whereKey($departmentId)
            ->value('manager_id');

        if ($managerId === null) {
            throw new InvalidArgumentException('واحد سازمانی مرحله مدیر فعال ندارد.');
        }

        return User::query()->findOrFail($managerId);
    }

    private function resolveProjectManager(WorkflowInstance $instance): User
    {
        if ($instance->project_id === null) {
            throw new InvalidArgumentException('برای مرحله مدیر پروژه، پروژه مشخص نشده است.');
        }

        $managerId = Project::query()
            ->whereKey($instance->project_id)
            ->value('manager_id');

        if ($managerId === null) {
            throw new InvalidArgumentException('پروژه مدیر فعال ندارد.');
        }

        return User::query()->findOrFail($managerId);
    }
}