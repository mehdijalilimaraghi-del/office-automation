<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Core\CustomFields\Services\CustomFieldService;
use App\Http\Requests\Modules\Tasks\AssignTaskRequest;
use App\Http\Requests\Modules\Tasks\ChangeTaskStatusRequest;
use App\Http\Requests\Modules\Tasks\ReferTaskRequest;
use App\Http\Requests\Modules\Tasks\ReviewTaskRequest;
use App\Http\Requests\Modules\Tasks\StoreTaskRequest;
use App\Http\Requests\Modules\Tasks\StoreTaskCommentRequest;
use App\Http\Requests\Modules\Tasks\StoreTaskReminderRequest;
use App\Http\Requests\Modules\Tasks\StoreTaskWorkLogRequest;
use App\Http\Requests\Modules\Tasks\UpdateTaskReminderStatusRequest;
use App\Http\Requests\Modules\Tasks\UpdateTaskRequest;
use App\Http\Requests\Modules\Tasks\StoreTaskActivityRequest;
use App\Modules\Tasks\Models\TaskActivity;
use App\Http\Requests\Modules\Tasks\UpdateTaskParticipantsRequest;
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
        private readonly CustomFieldService $customFieldService,
    ) {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Task::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:todo,in_progress,submitted,completed,cancelled'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'project_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
        ]);

        return Inertia::render('Tasks/Index', [
            'tasks' => $this->taskService->list($request->user(), $filters),
            'filters' => $filters,
            'canCreate' => Gate::allows('create', Task::class),
            'formOptions' => Gate::allows('create', Task::class)
                ? $this->taskFormOptionsService->forCreate($request->user())
                : null,
            'customFields' => $this->customFieldService->definitions(Task::class),
        ]);
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $data = $request->validated();

        abort_unless(
            Gate::allows('create', [
                Task::class,
                isset($data['project_id']) ? (int) $data['project_id'] : null,
            ]),
            403
        );

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
                'project.department',
                'documents.document.latestVersion',
                'assignee',
                'creator',
                'department.manager',
                'externalOrganization',
                'externalDepartment',
                'externalContacts.organization',
                'externalContacts.department',
                'assignments.fromUser',
                'assignments.toUser',
                'assignments.assignedBy',
                'comments.user',
                'workLogs.user',
                'reminders.user',
                'reminders.creator',
                'statusHistory.changedBy',
                'reviewedBy',
                'participants.user',
                'activities.assignee',
                'activities.creator',
                'activities.completedBy',
            ]),
            'timeline' => $this->taskService->timeline($task),
            'currentUserId' => (int) $request->user()->id,
            'abilities' => [
                'refer' => Gate::allows('refer', $task),
                'comment' => Gate::allows('comment', $task),
                'log_work' => Gate::allows('logWork', $task),
                'create_reminder' => Gate::allows('createReminder', $task),
                'change_status' => Gate::allows('changeStatus', $task),
                'submit' => Gate::allows('submit', $task),
                'review' => Gate::allows('review', $task),
                'update' => Gate::allows('update', $task),
                'delete' => Gate::allows('delete', $task),
                'documents_create' => Gate::allows('update', $task)
                    && Gate::allows('create', \App\Modules\Documents\Models\Document::class),
            ],
            'assigneeOptions' => $this->taskFormOptionsService->assigneesFor($task),
            'participantOptions' => $this->taskFormOptionsService->relatedUsers(),
            'activityAssigneeOptions' => $this->taskFormOptionsService->assigneesFor($task),
            'externalPartyOptions' => $this->taskFormOptionsService->forCreate($request->user()),
            'customFields' => $this->customFieldService->definitions(Task::class),
            'customFieldValues' => $this->customFieldService->values($task),
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

    public function updateExternalParties(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $data = $request->validate([
            'external_organization_id' => ['nullable', 'integer', 'exists:external_organizations,id'],
            'external_department_id' => ['nullable', 'integer', 'exists:external_departments,id'],
            'external_contact_ids' => ['nullable', 'array'],
            'external_contact_ids.*' => ['integer', 'distinct', 'exists:external_contacts,id'],
            'replace_external_contact_id' => ['nullable', 'integer', 'exists:external_contacts,id'],
        ]);

        $organizationId = $data['external_organization_id'] ?? null;
        $departmentId = $data['external_department_id'] ?? null;
        $contactIds = array_values(array_unique(array_map('intval', $data['external_contact_ids'] ?? [])));

        if ($departmentId !== null) {
            $departmentOrganizationId = (int) \App\Core\ExternalParties\Models\ExternalDepartment::query()
                ->whereKey($departmentId)
                ->value('external_organization_id');

            if ($organizationId === null || $departmentOrganizationId !== (int) $organizationId) {
                throw ValidationException::withMessages([
                    'external_department_id' => 'واحد خارجی انتخاب‌شده متعلق به سازمان انتخاب‌شده نیست.',
                ]);
            }
        }

        if ($contactIds !== []) {
            if ($organizationId === null) {
                throw ValidationException::withMessages([
                    'external_contact_ids' => 'برای انتخاب اشخاص خارجی، ابتدا سازمان خارجی را انتخاب کنید.',
                ]);
            }

            $contactsQuery = \App\Core\ExternalParties\Models\ExternalContact::query()
                ->whereIn('id', $contactIds)
                ->where('external_organization_id', $organizationId);

            if ($departmentId !== null) {
                $contactsQuery->where(function ($query) use ($departmentId): void {
                    $query->where('external_department_id', $departmentId)
                        ->orWhereNull('external_department_id');
                });
            }

            if ($contactsQuery->count() !== count($contactIds)) {
                throw ValidationException::withMessages([
                    'external_contact_ids' => 'یک یا چند شخص خارجی با سازمان یا واحد انتخاب‌شده مطابقت ندارند.',
                ]);
            }
        }

        if (isset($data['replace_external_contact_id'])) {
            $replaceContactId = (int) $data['replace_external_contact_id'];

            abort_unless(
                $task->externalContacts()->whereKey($replaceContactId)->exists(),
                404
            );

            $task->externalContacts()->detach($replaceContactId);
        }

        foreach ($contactIds as $contactId) {
            $task->externalContacts()->syncWithoutDetaching([
                $contactId => [
                    'external_organization_id' => $organizationId,
                    'external_department_id' => $departmentId,
                ],
            ]);
        }

        return back()->with('success', 'ارتباط و پیگیری بیرونی با موفقیت به‌روزرسانی شد.');
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

    public function storeActivity(StoreTaskActivityRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $data = $request->validated();
        $lastSequence = (int) $task->activities()->max('sequence');

        $task->activities()->create([
            'title' => trim($data['title']),
            'description' => $data['description'] ?? null,
            'status' => ($data['activity_type'] ?? 'planned') === 'logged' ? 'completed' : 'pending',
            'activity_type' => $data['activity_type'] ?? 'planned',
            'sequence' => $lastSequence + 1,
            'assigned_to' => $task->assignee_id,
            'created_by' => $request->user()->id,
            'completed_by' => ($data['activity_type'] ?? 'planned') === 'logged' ? $request->user()->id : null,
            'completed_at' => ($data['activity_type'] ?? 'planned') === 'logged' ? now() : null,
        ]);

        return back()->with('success', 'فعالیت با موفقیت ثبت شد.');
    }

    public function completeActivity(Request $request, Task $task, TaskActivity $activity): RedirectResponse
    {
        Gate::authorize('update', $task);
        abort_unless((int) $activity->task_id === (int) $task->id, 404);

        $activity->update([
            'status' => 'completed',
            'completed_by' => $request->user()->id,
            'completed_at' => now(),
        ]);

        return back()->with('success', 'فعالیت انجام شد.');
    }

    public function updateParticipants(UpdateTaskParticipantsRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $userIds = array_values(array_unique(array_map('intval', $request->validated()['user_ids'])));

        $task->participants()->delete();

        foreach ($userIds as $userId) {
            $task->participants()->create(['user_id' => $userId]);
        }

        return back()->with('success', 'افراد مرتبط وظیفه با موفقیت به‌روزرسانی شدند.');
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

    public function submit(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('submit', $task);

        try {
            $this->taskService->submit(task: $task, actor: $request->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['task' => $exception->getMessage()]);
        }

        return back()->with('success', 'تسک برای بررسی و تأیید ارسال شد.');
    }

    public function approve(Task $task, Request $request): RedirectResponse
    {
        Gate::authorize('review', $task);

        try {
            $this->taskService->approve(task: $task, reviewer: $request->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['task' => $exception->getMessage()]);
        }

        return back()->with('success', 'تسک تأیید و تکمیل شد.');
    }

    public function returnFromReview(ReviewTaskRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('review', $task);

        $note = trim((string) ($request->validated()['note'] ?? ''));
        if ($note === '') {
            throw ValidationException::withMessages(['note' => 'برای بازگرداندن تسک، درج توضیح الزامی است.']);
        }

        try {
            $this->taskService->returnFromReview(task: $task, reviewer: $request->user(), note: $note);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['task' => $exception->getMessage()]);
        }

        return back()->with('success', 'تسک برای اصلاح به مسئول آن بازگردانده شد.');
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
