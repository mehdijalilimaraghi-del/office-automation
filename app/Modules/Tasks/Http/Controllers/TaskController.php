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
use App\Modules\Tasks\Models\TaskStatusHistory;
use App\Http\Requests\Modules\Tasks\UpdateTaskParticipantsRequest;
use App\Models\User;
use App\Modules\Tasks\Contracts\TaskServiceContract;
use App\Modules\Tasks\Models\Task;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Services\CorrespondenceAccessService;
use App\Modules\Correspondence\Services\CorrespondenceWorkflowService;
use Illuminate\Support\Facades\DB;
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
            'status' => ['nullable', 'in:todo,observed,in_progress,completed,cancelled,cannot_continue'],
            'status_group' => ['nullable', 'in:pending'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'project_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'scope' => ['nullable', 'in:personal,unit'],
            'due' => ['nullable', 'in:today,overdue'],
            'review' => ['nullable', 'in:1'],
            'completed_period' => ['nullable', 'in:month'],
            'assignee_id' => ['nullable', 'integer', 'min:1'],
            'due_from' => ['nullable', 'date_format:Y-m-d'],
            'due_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if (! empty($filters['due_from']) && ! empty($filters['due_to']) && $filters['due_to'] < $filters['due_from']) {
            throw ValidationException::withMessages([
                'due_to' => 'تاریخ پایان باید مساوی یا بعد از تاریخ شروع باشد.',
            ]);
        }

        $canFilterUnit = in_array('organization', $request->user()->permissionScopes('tasks.view'), true)
            || $request->user()->managedDepartments()->exists()
            || $request->user()->managedProjects()->exists();

        if (! $canFilterUnit && ($filters['scope'] ?? null) === 'unit') {
            unset($filters['scope']);
        }

        return Inertia::render('Tasks/Index', [
            'tasks' => $this->taskService->list($request->user(), $filters),
            'filters' => $filters,
            'canCreate' => Gate::allows('create', Task::class),
            'canFilterUnit' => $canFilterUnit,
            'formOptions' => Gate::allows('create', Task::class)
                ? $this->taskFormOptionsService->forCreate($request->user())
                : null,
            'customFields' => $this->customFieldService->definitions(Task::class),
            'filterOptions' => $this->taskFormOptionsService->forFilters($request->user()),
        ]);
    }

    public function store(
        StoreTaskRequest $request,
        CorrespondenceAccessService $correspondenceAccess,
        CorrespondenceWorkflowService $correspondenceWorkflow,
    ): RedirectResponse {
        $data = $request->validated();
        $correspondenceId = isset($data['correspondence_id']) ? (int) $data['correspondence_id'] : null;
        unset($data['correspondence_id']);

        try {
            $task = DB::transaction(function () use ($data, $correspondenceId, $request, $correspondenceAccess, $correspondenceWorkflow): Task {
                $letter = null;

                if ($correspondenceId !== null) {
                    $letter = Correspondence::query()->findOrFail($correspondenceId);
                    abort_unless($correspondenceAccess->canView($letter, $request->user()), 403);
                    $section = isset($data['section_id'])
                        ? \\App\\Modules\\Projects\\Models\\ProjectSection::query()->with('project')->find($data['section_id'])
                        : null;
                    abort_unless($section !== null
                        && $section->project?->is_system_default
                        && $letter->current_department_id !== null
                        && (int) $section->department_id === (int) $letter->current_department_id, 403,
                        'دستورکار مرتبط باید در بخش واحد مسئول نامه از پروژه عمومی سازمان ایجاد شود.');
                }

                $task = $this->taskService->create(data: $data, creator: $request->user());

                if ($letter !== null) {
                    $correspondenceWorkflow->attachTaskInstruction($letter, $task, $request->user());
                }

                return $task;
            });
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['task' => $exception->getMessage()]);
        }

        if ($correspondenceId !== null) {
            return redirect()->route('correspondence.show', $correspondenceId)
                ->with('success', 'دستورکار در پروژه عمومی ایجاد و به نامه متصل شد.');
        }

        return back()->with('success', 'تسک با موفقیت ایجاد شد.');
    }

    public function show(Request $request, Task $task, CorrespondenceAccessService $correspondenceAccess): Response
    {
        Gate::authorize('view', $task);

        if ((int) $task->assignee_id === (int) $request->user()->id) {
            $fromStatus = $task->status;
            $notes = [];

            if ($task->status === 'todo') {
                $task->status = 'observed';
                $notes[] = 'دستورکار توسط مسئول مشاهده شد';
            }
            if ($task->sub_status === 'referred') {
                $task->sub_status = 'referral_seen';
                $notes[] = 'ارجاع مشاهده شد';
            }

            if ($notes !== []) {
                $task->save();
                TaskStatusHistory::query()->create([
                    'task_id' => $task->id,
                    'from_status' => $fromStatus,
                    'to_status' => $task->status,
                    'changed_by' => $request->user()->id,
                    'note' => implode('؛ ', $notes),
                    'changed_at' => now(),
                ]);
            }
        }

        $linkedCorrespondences = $task->correspondences()
            ->get()
            ->filter(fn (Correspondence $letter): bool => $correspondenceAccess->canView($letter, $request->user()))
            ->map(fn (Correspondence $letter): array => $letter->only(['id', 'subject', 'letter_number', 'type']))
            ->values();

        return Inertia::render('Tasks/Show', [
            'linkedCorrespondences' => $linkedCorrespondences,
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
                'comments.parent.user',
                'activities.creator',
                'activities.completedBy',
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
            'canCreateCorrespondence' => $request->user()->hasPermission('correspondence.create'),
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
                'documents_create' => Gate::allows('update', $task),
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
        $this->assertTaskContentEditable($request, $task);

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
        $this->assertTaskContentEditable($request, $task);

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
        $this->assertTaskContentEditable($request, $task);

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
        $this->assertTaskContentEditable($request, $task);

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

        if (in_array($task->status, ['todo', 'observed'], true)) {
            $task->update(['status' => 'in_progress', 'sub_status' => null]);
        } elseif ($task->sub_status !== null && $task->sub_status !== 'manager_review') {
            $task->update(['sub_status' => null]);
        }

        return back()->with('success', 'فعالیت با موفقیت ثبت شد.');
    }

    public function completeActivity(Request $request, Task $task, TaskActivity $activity): RedirectResponse
    {
        Gate::authorize('update', $task);
        $this->assertTaskContentEditable($request, $task);
        abort_unless((int) $activity->task_id === (int) $task->id, 404);

        abort_unless($activity->activity_type === 'planned' && $activity->status === 'pending', 422);

        $activity->update([
            'activity_type' => 'logged',
            'status' => 'completed',
            'completed_by' => $request->user()->id,
            'completed_at' => now(),
        ]);

        if (in_array($task->status, ['todo', 'observed'], true)) {
            $task->update(['status' => 'in_progress', 'sub_status' => null]);
        } elseif ($task->sub_status !== null && $task->sub_status !== 'manager_review') {
            $task->update(['sub_status' => null]);
        }

        return back()->with('success', 'فعالیت انجام شد و در گزارش فعالیت ثبت شد.');
    }

    public function updateActivity(Request $request, Task $task, TaskActivity $activity): RedirectResponse
    {
        Gate::authorize('update', $task);
        $this->assertTaskContentEditable($request, $task);
        abort_unless((int) $activity->task_id === (int) $task->id, 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
        ]);

        $activity->update([
            'title' => trim($data['title']),
            'description' => $data['description'] ?? null,
            'started_at' => array_key_exists('started_at', $data) ? $data['started_at'] : $activity->started_at,
            'completed_at' => array_key_exists('completed_at', $data) ? $data['completed_at'] : $activity->completed_at,
        ]);

        return back()->with('success', 'فعالیت با موفقیت ویرایش شد.');
    }

    public function destroyActivity(Request $request, Task $task, TaskActivity $activity): RedirectResponse
    {
        Gate::authorize('update', $task);
        $this->assertTaskContentEditable($request, $task);
        abort_unless((int) $activity->task_id === (int) $task->id, 404);

        $activity->delete();

        return back()->with('success', 'فعالیت با موفقیت حذف شد.');
    }

    public function updateParticipants(UpdateTaskParticipantsRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);
        $this->assertTaskContentEditable($request, $task);

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

        $parentCommentId = $request->validated()['parent_comment_id'] ?? null;
        if ($parentCommentId !== null && ! $task->comments()->whereKey($parentCommentId)->exists()) {
            throw ValidationException::withMessages([
                'parent_comment_id' => 'دیدگاه انتخاب‌شده متعلق به این دستورکار نیست.',
            ]);
        }

        $this->taskService->addComment(
            task: $task,
            author: $request->user(),
            body: $request->validated()['body'],
            parentCommentId: $request->validated()['parent_comment_id'] ?? null,
        );

        return back()->with('success', 'نظر با موفقیت ثبت شد.');
    }

    public function updateComment(Request $request, Task $task, \App\Modules\Tasks\Models\TaskComment $comment): RedirectResponse
    {
        Gate::authorize('view', $task);
        abort_unless((int) $comment->task_id === (int) $task->id, 404);
        abort_unless((int) $comment->user_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        abort_if($task->isFinalized(), 403, 'نظر دستورکار نهایی‌شده قابل تغییر نیست.');
        abort_if($task->review_status === 'pending', 403, 'تا تعیین تکلیف مدیر، نظر قابل تغییر نیست.');
        $comment->update(['body' => $data['body']]);

        return back()->with('success', 'نظر با موفقیت ویرایش شد.');
    }

    public function destroyComment(Request $request, Task $task, \App\Modules\Tasks\Models\TaskComment $comment): RedirectResponse
    {
        Gate::authorize('view', $task);
        abort_unless((int) $comment->task_id === (int) $task->id, 404);
        abort_unless((int) $comment->user_id === (int) $request->user()->id, 403);

        abort_if($task->isFinalized(), 403, 'نظر دستورکار نهایی‌شده قابل تغییر نیست.');
        abort_if($task->review_status === 'pending', 403, 'تا تعیین تکلیف مدیر، نظر قابل تغییر نیست.');
        // SQL Server rejects cascading self-references; detach replies before deleting their parent.
        $comment->replies()->update(['parent_comment_id' => null]);
        $comment->delete();

        return back()->with('success', 'نظر با موفقیت حذف شد.');
    }

    public function updateReminder(Request $request, Task $task, TaskReminder $reminder): RedirectResponse
    {
        Gate::authorize('update', $task);
        $this->assertTaskContentEditable($request, $task);
        abort_unless((int) $reminder->task_id === (int) $task->id, 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'remind_at' => ['required', 'date'],
        ]);

        $reminder->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'remind_at' => $data['remind_at'],
        ]);

        return back()->with('success', 'یادآوری با موفقیت ویرایش شد.');
    }

    public function destroyReminder(Request $request, Task $task, TaskReminder $reminder): RedirectResponse
    {
        Gate::authorize('update', $task);
        $this->assertTaskContentEditable($request, $task);
        abort_unless((int) $reminder->task_id === (int) $task->id, 404);

        $reminder->delete();

        return back()->with('success', 'یادآوری با موفقیت حذف شد.');
    }

    public function workLog(StoreTaskWorkLogRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('logWork', $task);
        $this->assertTaskContentEditable($request, $task);
        $data = $request->validated();
        $sequence = ((int) $task->activities()->max('sequence')) + 1;

        $task->activities()->create([
            'title' => trim($data['title']),
            'description' => $data['description'] ?? null,
            'status' => 'completed',
            'activity_type' => 'logged',
            'sequence' => $sequence,
            'assigned_to' => $task->assignee_id,
            'created_by' => $request->user()->id,
            'completed_by' => $request->user()->id,
            'started_at' => $data['started_at'] ?? null,
            'completed_at' => $data['completed_at'] ?? null,
        ]);

        if (in_array($task->status, ['todo', 'observed'], true)) {
            $task->update(['status' => 'in_progress', 'sub_status' => null]);
        } elseif ($task->sub_status !== null && $task->sub_status !== 'manager_review') {
            $task->update(['sub_status' => null]);
        }

        return back()->with('success', 'فعالیت انجام‌شده با موفقیت ثبت شد.');
    }


    public function reminder(StoreTaskReminderRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('createReminder', $task);
        $this->assertTaskContentEditable($request, $task);
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
        abort_if($task->isFinalized(), 403, 'یادآوری دستورکار نهایی‌شده قابل تغییر نیست.');

        Gate::authorize(
            $status === 'completed' ? 'completeReminder' : 'cancelReminder',
            [$task, $reminder],
        );
        abort_if($task->review_status === 'pending', 403, 'یادآوری این دستورکار در زمان بررسی قابل تغییر نیست.');
        abort_unless((int) $reminder->user_id === (int) $request->user()->id, 403);

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

    private function assertTaskContentEditable(Request $request, Task $task): void
    {
        abort_if($task->isFinalized(), 403, 'دستورکار نهایی‌شده و قابل تغییر نیست.');
        abort_if($task->review_status === 'pending', 403, 'این دستورکار تا زمان برگشت برای اصلاح یا تعیین تکلیف مدیر قابل تغییر نیست.');
        abort_unless((int) $task->assignee_id === (int) $request->user()->id, 403, 'فقط مسئول فعلی دستورکار می‌تواند فعالیت‌ها و اطلاعات اجرایی آن را تغییر دهد.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        Gate::authorize('delete', $task);
        $this->taskService->delete($task);

        return back()->with('success', 'تسک با موفقیت حذف شد.');
    }
}
