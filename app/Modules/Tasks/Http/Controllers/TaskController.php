<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\Tasks\AssignTaskRequest;
use App\Http\Requests\Modules\Tasks\ChangeTaskStatusRequest;
use App\Http\Requests\Modules\Tasks\StoreTaskRequest;
use App\Http\Requests\Modules\Tasks\UpdateTaskRequest;
use App\Modules\Tasks\Contracts\TaskServiceContract;
use App\Modules\Tasks\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskServiceContract $taskService,
    ) {
    }

    public function index(): Response
    {
        Gate::authorize('viewAny', Task::class);

        return Inertia::render('Tasks/Index', [
            'tasks' => $this->taskService->list(),
        ]);
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Gate::authorize('create', [
            Task::class,
            (int) $data['project_id'],
        ]);

        try {
            $this->taskService->create(
                data: $data,
                creator: $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'task' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'تسک با موفقیت ایجاد شد.'
        );
    }

    public function show(Task $task): Response
    {
        Gate::authorize('view', $task);

        return Inertia::render('Tasks/Show', [
            'task' => $task->load([
                'project',
                'assignee',
                'creator',
            ]),
        ]);
    }

    public function update(
        UpdateTaskRequest $request,
        Task $task
    ): RedirectResponse {
        Gate::authorize('update', $task);

        try {
            $this->taskService->update(
                task: $task,
                data: $request->validated(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'task' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'تسک با موفقیت ویرایش شد.'
        );
    }

    public function assign(
        AssignTaskRequest $request,
        Task $task
    ): RedirectResponse {
        Gate::authorize('assign', $task);

        $assignee = \App\Models\User::query()->findOrFail(
            $request->validated()['assignee_id']
        );

        try {
            $this->taskService->assign(
                task: $task,
                assignee: $assignee,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'assignee_id' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'مسئول تسک با موفقیت تغییر کرد.'
        );
    }

    public function changeStatus(
        ChangeTaskStatusRequest $request,
        Task $task
    ): RedirectResponse {
        Gate::authorize('changeStatus', $task);

        try {
            $this->taskService->changeStatus(
                task: $task,
                status: $request->validated()['status'],
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'status' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'وضعیت تسک با موفقیت تغییر کرد.'
        );
    }

    public function destroy(Task $task): RedirectResponse
    {
        Gate::authorize('delete', $task);

        $this->taskService->delete($task);

        return back()->with(
            'success',
            'تسک با موفقیت حذف شد.'
        );
    }
}
