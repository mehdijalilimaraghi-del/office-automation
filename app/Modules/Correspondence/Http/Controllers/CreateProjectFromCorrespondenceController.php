<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Http\Controllers;

use App\Core\Departments\Models\Department;
use App\Core\Settings\Services\OrganizationDepartmentRoleService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Models\CorrespondenceAction;
use App\Modules\Projects\Actions\CreateProjectAction;
use App\Modules\Tasks\Actions\CreateTaskAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CreateProjectFromCorrespondenceController extends Controller
{
    public function __invoke(
        Request $request,
        Correspondence $correspondence,
        CreateProjectAction $createProject,
        CreateTaskAction $createTask,
    ): RedirectResponse {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:10000'],
            'response_department_id' => ['required', 'integer', 'exists:departments,id'],
        ]);

        $actor = $request->user();
        $roles = app(OrganizationDepartmentRoleService::class);
        $projectManagementDepartmentId = $roles->activeDepartmentId(OrganizationDepartmentRoleService::PROJECT_MANAGEMENT);

        abort_unless(
            $actor instanceof User
                && $actor->isActive()
                && $actor->hasPermission('projects.create')
                && $projectManagementDepartmentId !== null
                && $roles->userBelongsTo($actor, OrganizationDepartmentRoleService::PROJECT_MANAGEMENT),
            403,
            'ایجاد پروژه فقط برای کاربران مجاز واحد مدیریت پروژه امکان‌پذیر است.'
        );

        abort_unless(
            $correspondence->status === Correspondence::STATUS_REGISTERED
                && $correspondence->type === Correspondence::TYPE_INCOMING
                && $correspondence->workflow_state === 'project_request_approved'
                && (int) $correspondence->current_department_id === (int) $projectManagementDepartmentId
                && (int) Department::query()->whereKey($projectManagementDepartmentId)->value('manager_id') === (int) $actor->id,
            403,
            'این نامه در وضعیت مجاز برای ایجاد پروژه نیست یا شما مدیر واحد مدیریت پروژه نیستید.'
        );

        if ($correspondence->project_id !== null) {
            throw ValidationException::withMessages([
                'project' => 'برای این نامه قبلاً پروژه ایجاد شده است.',
            ]);
        }

        $department = Department::query()
            ->with('manager')
            ->whereKey($data['response_department_id'])
            ->where('is_active', true)
            ->first();

        if ($department === null || $department->manager === null || ! $department->manager->isActive()) {
            throw ValidationException::withMessages([
                'response_department_id' => 'واحد پاسخ‌گو باید فعال و دارای مدیر فعال باشد.',
            ]);
        }

        try {
            $project = DB::transaction(function () use ($data, $actor, $department, $correspondence, $projectManagementDepartmentId, $createProject, $createTask): \App\Modules\Projects\Models\Project {
                $project = $createProject->execute([
                    'department_id' => null,
                    'name' => $data['name'],
                    'description' => $data['description'] ?? ('پروژه ایجادشده برای پاسخ به نامه: '.$correspondence->subject),
                    'manager_id' => $actor->id,
                    'is_active' => true,
                    'sections' => [[
                        'name' => $department->name,
                        'department_id' => $department->id,
                        'description' => 'بخش پاسخ‌گویی به نامه وارده',
                        'manager_can_create_tasks' => true,
                    ]],
                ], $actor);

                $section = $project->sections()->firstOrFail();
                $task = $createTask->execute([
                    'project_id' => $project->id,
                    'section_id' => $section->id,
                    'department_id' => $department->id,
                    'title' => 'تهیه پاسخ نامه: '.$correspondence->subject,
                    'description' => 'تهیه و ارسال پاسخ برای نامه وارده شماره '.($correspondence->registration_number ?? $correspondence->id),
                    'status' => 'todo',
                    'priority' => 'normal',
                    'is_active' => true,
                ], $actor);

                $correspondence->forceFill([
                    'project_id' => $project->id,
                    'task_id' => $task->id,
                    'current_department_id' => $department->id,
                    'current_user_id' => $department->manager_id,
                    'workflow_state' => 'project_created',
                ])->save();

                CorrespondenceAction::query()->create([
                    'correspondence_id' => $correspondence->id,
                    'actor_id' => $actor->id,
                    'action_type' => 'project_created',
                    'note' => 'پروژه و تسک پاسخ برای واحد '.$department->name.' ایجاد شد.',
                    'from_department_id' => $projectManagementDepartmentId,
                    'to_department_id' => $department->id,
                    'from_user_id' => $actor->id,
                    'to_user_id' => $department->manager_id,
                    'metadata' => [
                        'project_id' => (int) $project->id,
                        'task_id' => (int) $task->id,
                        'response_department_id' => (int) $department->id,
                    ],
                ]);

                return $project;
            });
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'project' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('projects.show', $project)
            ->with('success', 'پروژه و تسک پاسخ با موفقیت ایجاد و به نامه مبدأ متصل شدند.');
    }
}
