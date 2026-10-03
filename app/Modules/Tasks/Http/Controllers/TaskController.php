<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\Tasks\AssignTaskRequest;
use App\Http\Requests\Modules\Tasks\ChangeTaskStatusRequest;
use App\Http\Requests\Modules\Tasks\ReferTaskRequest;
use App\Http\Requests\Modules\Tasks\StoreTaskRequest;
use App\Http\Requests\Modules\Tasks\StoreTaskCommentRequest;
use App\Http\Requests\Modules\Tasks\StoreTaskReminderRequest;
use App\Http\Requests\Modules\Tasks\StoreTaskWorkLogRequest;
use App\Http\Requests\Modules\Tasks\UpdateTaskReminderStatusRequest;
use App\Http\Requests\Modules\Tasks\UpdateTaskRequest;
use App\Models\User;
use App\Modules\Tasks\Contracts\TaskServiceContract;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskReminder;
use App\Modules\Tasks\Services\TaskFormOptionsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskServiceContract $taskService,
        private readonly TaskFormOptionsService $taskFormOptionsService,
    ) {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Task::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:todo,in_progress,completed,cancelled'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'project_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
        ]);

        return Inertia::render('Tasks/Index', [
            'tasks' => $this->taskService->list($request->user(), $filters),
                'search' => ['nullable', 'string', 'max:255'],
                'status' => ['nullable', 'in:todo,in_progress,completed,cancelled'],
                'priority' => ['nullable', 'in:low,normal,high,urgent'],
                'project_id' => ['nullable', 'integer'],
                'department_id' => ['nullable', 'integer'],
            ]),
            'filters' => $filters,
            'canCreate' => Gate::allows('create', Task::class),
            'formOptions' => Gate::allows('create', Task::class)
                ? $this->taskFormOptionsService->forCreate($request->user())
                : null,
        ]);
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Gate::authorize('create', [
            Task::class,
            isset($data['project_id']) ? (int) $data['project_id'] : null,
        ]);

        try {
            $this->taskService->create(data: $data, creator: $request->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['task' => $exception->getMessage()]);
        }

        return back()->with('success', 'تسک با موفقیت ایجاد شد.');
    }

    public function show(Request $request, Task $task): Response
    {
        Gate::authorize('view', $task);

        return Inertia::render('Tasks/Show', [
            'task' => $task->load([
                'project',
                'assignee',
                'creator',
                'department',
                'assignments.fromUser',
                'assignments.toUser',
                'assignments.assignedBy',
                'comments.user',
                'workLogs.user',
                'reminders.user',
                'reminders.creator',
                'statusHistory.changedBy',
            ]),
            'timeline' => $this->taskService->timeline($task),
            'currentUserId' => (int) $request->user()->id,
            'abilities' => [
                'refer' => Gate::allows('refer', $task),
                'comment' => Gate::allows('comment', $task),
                'log_work' => Gate::allows('logWork', $task),
                'create_reminder' => Gate::allows('createReminder', $task),
                'change_status' => Gate::allows('changeStatus', $task),
                'update' => Gate::allows('update', $task),
                'delete' => Gate::allows('delete', $task),
            ],
            'assigneeOptions' => $this->taskFormOptionsService->assigneesFor($task),
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        try {
            $this->taskService->update(task: $task, data: $request->validated());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['task' => $exception->getMessage()]);
        }

        return back()->with('success', 'تسک با موفقیت ویرایش شد.');
    }

    public function assign(AssignTaskRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('assign', $task);

        $assignee = User::query()->findOrFail($request->validated()['assignee_id']);

        try {
            $this->taskService->assign(task: $task, assignee: $assignee, actor: $request->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['assignee_id' => $exception->getMessage()]);
        }

        return back()->with('success', 'مسئول تسک با موفقیت تغییر کرد.');
    }

    public function refer(ReferTaskRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('refer', $task);

        $assignee = User::query()->findOrFail($request->validated()['assignee_id']);

        try {
            $this->taskService->assign(
                task: $task,
                assignee: $assignee,
                actor: $request->user(),
                type: 'handoff',
                note: $request->validated()['note'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['assignee_id' => $exception->getMessage()]);
        }

        return back()->with('success', 'تسک با موفقیت ارجاع شد.');
    }

    public function comment(StoreTaskCommentRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('comment', $task);

        $this->taskService->addComment(
            task: $task,
            author: $request->user(),
            body: $request->validated()['body'],
        );

        return back()->with('success', 'نظر با موفقیت ثبت شد.');
    }

    public function workLog(StoreTaskWorkLogRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('logWork', $task);
        $data = $request->validated();

        $this->taskService->addWorkLog(
            task: $task,
            user: $request->user(),
            title: $data['title'],
            description: $data['description'] ?? null,
            startedAt: $data['started_at'] ?? null,
            completedAt: $data['completed_at'] ?? null,
        );

        return back()->with('success', 'گزارش فعالیت با موفقیت ثبت شد.');
    }

    public function reminder(StoreTaskReminderRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('createReminder', $task);
        $data = $request->validated();
        $recipient = User::query()->findOrFail($data['user_id']);

        $this->taskService->addReminder(
            task: $task,
            recipient: $recipient,
            creator: $request->user(),
            title: $data['title'],
            remindAt: $data['remind_at'],
            description: $data['description'] ?? null,
        );

        return back()->with('success', 'یادآور با موفقیت ایجاد شد.');
    }

    public function updateReminderStatus(
        UpdateTaskReminderStatusRequest $request,
        Task $task,
        TaskReminder $reminder,
    ): RedirectResponse {
        $status = $request->validated()['status'];

        Gate::authorize(
            $status === 'completed' ? 'completeReminder' : 'cancelReminder',
            [$task, $reminder],
        );

        try {
            $this->taskService->updateReminderStatus(
                reminder: $reminder,
                status: $status,
                actor: $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return back()->with(
            'success',
            $status === 'completed' ? 'یادآور تکمیل شد.' : 'یادآور لغو شد.',
        );
    }

    public function changeStatus(ChangeTaskStatusRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('changeStatus', $task);

        try {
            $this->taskService->changeStatus(
                task: $task,
                status: $request->validated()['status'],
                actor: $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return back()->with('success', 'وضعیت تسک با موفقیت تغییر کرد.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        Gate::authorize('delete', $task);
        $this->taskService->delete($task);

        return back()->with('success', 'تسک با موفقیت حذف شد.');
    }
}
