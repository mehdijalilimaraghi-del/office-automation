<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Http\Controllers;

use App\Core\Audit\Contracts\AuditServiceInterface;
use App\Core\Departments\Models\Department;
use App\Core\ExternalParties\Models\ExternalOrganization;
use App\Core\ExternalParties\Models\ExternalDepartment;
use App\Http\Controllers\Controller;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Models\CorrespondenceAction;
use App\Modules\Tasks\Models\Task;
use App\Modules\Correspondence\Services\CorrespondenceNumberingService;
use App\Modules\Correspondence\Services\CorrespondenceAccessService;
use App\Modules\Correspondence\Services\CorrespondenceService;
use App\Modules\Correspondence\Services\CorrespondenceWorkflowService;
use App\Modules\Correspondence\Http\Controllers\CorrespondenceScannerController;
use App\Core\Settings\Services\SystemSettingsService;
use App\Modules\Documents\Services\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class CorrespondenceController extends Controller
{
    public function index(Request $request, CorrespondenceAccessService $access): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'type' => ['nullable', Rule::in(Correspondence::allowedTypes())],
            'status' => ['nullable', Rule::in([Correspondence::STATUS_DRAFT, Correspondence::STATUS_REGISTERED])],
            'scope' => ['nullable', Rule::in(['mine', 'department', 'all'])],
        ]);

        $scope = $filters['scope'] ?? 'mine';

        $user = $request->user();
        $departmentIds = $user->departments()->pluck('departments.id')
            ->merge($user->managedDepartments()->pluck('departments.id'))
            ->unique()
            ->values();

        $query = $access->visibleToQuery(Correspondence::query(), $user)
            ->when($scope === 'mine', function ($query) use ($user): void {
                $query->where(function ($mine) use ($user): void {
                    $mine->where('created_by', $user->id)
                        ->orWhere('registered_by', $user->id)
                        ->orWhere('current_user_id', $user->id)
                        ->orWhereHas('authorizedUsers', fn ($users) => $users->where('users.id', $user->id))
                        ->orWhereHas('actions', fn ($actions) => $actions->where('actor_id', $user->id));
                });
            })
            ->when($scope === 'department', function ($query) use ($departmentIds): void {
                if ($departmentIds->isEmpty()) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->where(function ($department) use ($departmentIds): void {
                    $department->whereIn('created_department_id', $departmentIds)
                        ->orWhereIn('recipient_department_id', $departmentIds)
                        ->orWhereIn('current_department_id', $departmentIds)
                        ->orWhereHas('copies', fn ($copies) => $copies->whereIn('departments.id', $departmentIds))
                        ->orWhereHas('actions', fn ($actions) => $actions
                            ->whereIn('from_department_id', $departmentIds)
                            ->orWhereIn('to_department_id', $departmentIds));
                });
            })
            ->with(['creator:id,name', 'createdDepartment:id,name', 'recipientDepartment:id,name', 'externalOrganization:id,name', 'externalDepartment:id,name,external_organization_id', 'registrar:id,name'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('subject', 'like', '%'.$search.'%')
                        ->orWhere('registration_number', 'like', '%'.$search.'%')
                        ->orWhere('letter_number', 'like', '%'.$search.'%')
                        ->orWhere('sender_name', 'like', '%'.$search.'%')
                        ->orWhere('recipient_name', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('created_at');

        $correspondences = $query->paginate(15)->withQueryString();
        $correspondences->getCollection()->transform(function (Correspondence $correspondence) use ($access, $request): Correspondence {
            $correspondence->setAttribute('can_register', $access->canRegister($correspondence, $request->user()));

            return $correspondence;
        });

        return Inertia::render('Correspondence/Index', [
            'correspondences' => $correspondences,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'type' => $filters['type'] ?? '',
                'status' => $filters['status'] ?? '',
                'scope' => $scope,
            ],
            'canCreate' => $request->user()->hasPermission('correspondence.create'),
        ]);
    }

    public function show(Request $request, Correspondence $correspondence, CorrespondenceAccessService $access, SystemSettingsService $settings): Response
    {
        abort_unless($access->canView($correspondence, $request->user()), 403);

        $correspondence->load([
            'creator:id,name',
            'registrar:id,name',
            'createdDepartment:id,name',
            'recipientDepartment:id,name',
            'externalOrganization:id,name',
            'externalDepartment:id,name,external_organization_id',
            'replyTo:id,subject,letter_number,registration_number,type,status',
            'replies:id,subject,letter_number,registration_number,type,status,reply_to_id',
            'task:id,title,status,assignee_id,created_by',
            'documents.document.latestVersion',
            'actions.actor:id,name',
            'actions.fromDepartment:id,name',
            'actions.toDepartment:id,name',
            'copies:id,name',
        ]);

        return Inertia::render('Correspondence/Show', [
            'correspondence' => [
                'id' => $correspondence->id,
                'type' => $correspondence->type,
                'classification' => $correspondence->classification,
                'subject' => $correspondence->subject,
                'body' => $correspondence->body,
                'status' => $correspondence->status,
                'letter_number' => $correspondence->letter_number,
                'registration_number' => $correspondence->registration_number,
                'registered_at' => $correspondence->registered_at,
                'created_at' => $correspondence->created_at,
                'sender_name' => $correspondence->sender_name,
                'recipient_name' => $correspondence->recipient_name,
                'received_at' => $correspondence->received_at,
                'sent_at' => $correspondence->sent_at,
                'task' => $correspondence->task,
                'task_id' => $correspondence->task_id,
                'external_registration_number' => $correspondence->external_registration_number,
                'external_registered_at' => $correspondence->external_registered_at?->toDateString(),
                'dispatch_method' => $correspondence->dispatch_method,
                'external_tracking_notes' => $correspondence->external_tracking_notes,
                'creator' => $correspondence->creator,
                'registrar' => $correspondence->registrar,
                'created_department' => $correspondence->createdDepartment,
                'recipient_department' => $correspondence->recipientDepartment,
                'external_organization' => $correspondence->externalOrganization,
                'external_department' => $correspondence->externalDepartment,
                'reply_to' => $correspondence->replyTo !== null && $access->canView($correspondence->replyTo, $request->user())
                    ? $correspondence->replyTo
                    : null,
                'replies' => $correspondence->replies
                    ->filter(fn (Correspondence $reply) => $access->canView($reply, $request->user()))
                    ->values(),
                'workflow_state' => $correspondence->workflow_state,
                'current_department' => $correspondence->current_department_id ? Department::query()->find($correspondence->current_department_id, ['id', 'name']) : null,
                'current_user_id' => $correspondence->current_user_id,
                'actions' => $correspondence->actions,
                'copies' => $correspondence->copies,
            ],
            'originalLetters' => $correspondence->documents
                ->filter(fn ($link) => $link->purpose === 'correspondence_original' && $link->document !== null && $link->document->latestVersion !== null)
                ->map(fn ($link) => [
                    'id' => $link->document->id,
                    'title' => $link->document->title,
                    'file_name' => $link->document->latestVersion->original_name,
                    'size' => $link->document->latestVersion->size,
                ])
                ->values(),
            'attachments' => $correspondence->documents
                ->filter(fn ($link) => $link->purpose !== 'correspondence_original' && $link->document !== null && $link->document->latestVersion !== null)
                ->map(fn ($link) => [
                    'id' => $link->document->id,
                    'title' => $link->document->title,
                    'file_name' => $link->document->latestVersion->original_name,
                    'size' => $link->document->latestVersion->size,
                ])
                ->values(),
            'canRegister' => $access->canRegister($correspondence, $request->user()),
            'workflowDepartments' => Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canReturnToManager' => $correspondence->workflow_state !== 'completed_with_result'
                && $correspondence->status === Correspondence::STATUS_REGISTERED
                && in_array($correspondence->type, [Correspondence::TYPE_INCOMING, Correspondence::TYPE_INTERNAL], true)
                && $correspondence->current_department_id !== null
                && (int) Department::query()->whereKey($correspondence->current_department_id)->value('manager_id') !== (int) $request->user()->id
                && ($request->user()->departments()->where('departments.id', $correspondence->current_department_id)->exists()
                    || $request->user()->managedDepartments()->whereKey($correspondence->current_department_id)->exists()),
            'canManageWorkflow' => $correspondence->workflow_state !== 'completed_with_result'
                && $correspondence->current_department_id !== null
                && (int) Department::query()->whereKey($correspondence->current_department_id)->value('manager_id') === (int) $request->user()->id,
            'canAddCopies' => $correspondence->workflow_state !== 'completed_with_result'
                && $correspondence->current_department_id !== null
                && (int) Department::query()->whereKey($correspondence->current_department_id)->value('manager_id') === (int) $request->user()->id,
            'copyDepartments' => Department::query()
                ->where('is_active', true)
                ->whereNotIn('id', $correspondence->copies()->select('departments.id'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'trackableTasks' => Task::query()
                ->where(function ($query) use ($request): void {
                    $query->where('created_by', $request->user()->id)
                        ->orWhere('assignee_id', $request->user()->id);
                })
                ->whereNotIn('status', ['cancelled', 'cannot_continue'])
                ->orderByDesc('created_at')
                ->limit(100)
                ->get(['id', 'title', 'status']),
            'canUpdateExternalTracking' => $request->user()->isSystemAdmin()
                || $access->isSecretariat($request->user())
                || (int) $correspondence->created_by === (int) $request->user()->id
                || (int) $correspondence->task?->assignee_id === (int) $request->user()->id
                || (int) $correspondence->task?->created_by === (int) $request->user()->id,
            'correspondenceFontFamily' => $settings->get(['correspondence.composition.font_family' => 'Vazirmatn'])['correspondence.composition.font_family'],
        ]);
    }

    public function create(Request $request, CorrespondenceAccessService $access, SystemSettingsService $settings): Response
    {
        $types = array_values(array_filter([
            ['value' => Correspondence::TYPE_INCOMING, 'label' => 'وارده'],
            ['value' => Correspondence::TYPE_OUTGOING, 'label' => 'صادره'],
            ['value' => Correspondence::TYPE_INTERNAL, 'label' => 'داخلی'],
        ], fn (array $type): bool => $access->canCreateType($type['value'], $request->user())));

        $createdDepartments = $request->user()->departments()
            ->where('departments.is_active', true)
            ->orderBy('departments.name')
            ->get(['departments.id', 'departments.name'])
            ->keyBy('id');

        $managedDepartments = $request->user()->managedDepartments()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        foreach ($managedDepartments as $department) {
            $createdDepartments->put($department->id, $department);
        }

        $createdDepartments = $createdDepartments->sortBy('name')->values();
        $createdDepartment = $request->user()->departments()
            ->wherePivot('is_primary', true)
            ->where('departments.is_active', true)
            ->first(['departments.id', 'departments.name']);

        if ($createdDepartment === null && $createdDepartments->count() === 1) {
            $createdDepartment = $createdDepartments->first();
        }

        return Inertia::render('Correspondence/Create', [
            'correspondenceFontFamily' => $settings->get(['correspondence.composition.font_family' => 'Vazirmatn'])['correspondence.composition.font_family'],
            'createdDepartment' => $createdDepartment,
            'createdDepartments' => $createdDepartments,
            'externalOrganizations' => ExternalOrganization::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'externalDepartments' => ExternalDepartment::query()->where('status', 'active')->orderBy('name')->get(['id', 'external_organization_id', 'name']),
            'types' => $types,
            'classifications' => [
                ['value' => Correspondence::CLASSIFICATION_ORDINARY, 'label' => 'عادی'],
                ['value' => Correspondence::CLASSIFICATION_CONFIDENTIAL, 'label' => 'محرمانه'],
            ],
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'repliesTo' => $access->visibleToQuery(Correspondence::query(), $request->user())
                ->where('status', Correspondence::STATUS_REGISTERED)
                ->orderByDesc('registered_at')
                ->limit(100)
                ->get(['id', 'letter_number', 'registration_number', 'subject']),
        ]);
    }

    public function store(
        Request $request,
        CorrespondenceService $service,
        DocumentService $documents,
        CorrespondenceAccessService $access,
    ): RedirectResponse {
        $requestedType = $request->input('type');
        if (is_string($requestedType) && in_array($requestedType, Correspondence::allowedTypes(), true)) {
            abort_unless($access->canCreateType($requestedType, $request->user()), 403);
        }

        $allowedCreatedDepartmentIds = $request->user()->departments()
            ->where('departments.is_active', true)
            ->pluck('departments.id')
            ->merge($request->user()->managedDepartments()->where('is_active', true)->pluck('id'))
            ->unique()
            ->values()
            ->all();

        $createdDepartmentRules = ['nullable', 'integer', Rule::in($allowedCreatedDepartmentIds)];
        if (count($allowedCreatedDepartmentIds) > 1 && $request->input('type') !== Correspondence::TYPE_INCOMING) {
            $createdDepartmentRules[0] = 'required';
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(Correspondence::allowedTypes())],
            'created_department_id' => $createdDepartmentRules,
            'classification' => ['sometimes', 'required', Rule::in([Correspondence::CLASSIFICATION_ORDINARY, Correspondence::CLASSIFICATION_CONFIDENTIAL])],
            'subject' => ['required', 'string', 'max:255'],
            'composition_mode' => [Rule::requiredIf($request->input('type') !== Correspondence::TYPE_INCOMING), 'nullable', Rule::in(['editor', 'file', 'both'])],
            'body' => ['nullable', 'string', 'max:100000'],
            'sender_name' => ['nullable', 'string', 'max:255'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'external_organization_id' => ['nullable', 'integer', Rule::exists('external_organizations', 'id')->where('status', 'active')],
            'external_department_id' => ['nullable', 'integer', Rule::exists('external_departments', 'id')->where(fn ($query) => $query->where('status', 'active')->where('external_organization_id', $request->input('external_organization_id')))],
            'received_at' => ['nullable', 'date'],
            'sent_at' => ['nullable', 'date'],
            'recipient_department_id' => ['nullable', 'required_if:type,internal', 'required_if:type,incoming', 'integer', 'exists:departments,id'],
            'reply_to_id' => ['nullable', 'integer', Rule::exists('correspondences', 'id')->where('status', Correspondence::STATUS_REGISTERED)],
            'copy_department_ids' => ['nullable', 'array', 'max:20'],
            'copy_department_ids.*' => ['integer', 'distinct', Rule::exists('departments', 'id')->where('is_active', true)],
            'scanner_upload_token' => ['nullable', 'string', 'max:100'],
            'original_letters' => ['nullable', 'array', 'max:10'],
            'original_letters.*' => ['file', 'max:20480', 'mimes:pdf,jpg,jpeg,png'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => [
                'file',
                'max:20480',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,txt,csv,zip',
            ],
        ]);

        abort_unless($access->canCreateType($data['type'], $request->user()), 403);

        $mode = $data['type'] === Correspondence::TYPE_INCOMING
            ? 'file'
            : ($data['composition_mode'] ?? 'editor');
        $body = $mode === 'file' ? '' : $this->sanitizeBody((string) ($data['body'] ?? ''));

        if ($data['type'] !== Correspondence::TYPE_INCOMING && in_array($mode, ['editor', 'both'], true) && trim(strip_tags($body)) === '') {
            return back()->withErrors(['body' => 'متن نامه را وارد کنید.'])->withInput();
        }

        $originalLetters = array_values(array_filter((array) $request->file('original_letters', [])));
        $scannerUpload = null;
        if (! empty($data['scanner_upload_token'])) {
            $scannerUpload = CorrespondenceScannerController::consume($data['scanner_upload_token'], $request->user());
            if ($scannerUpload === null || $data['type'] !== Correspondence::TYPE_INCOMING) {
                return back()->withErrors(['scanner_upload_token' => 'فایل اسکن‌شده معتبر نیست یا مهلت آن پایان یافته است؛ دوباره اسکن کنید.'])->withInput();
            }

            $originalLetters[] = new UploadedFile(
                Storage::disk('local')->path($scannerUpload['path']),
                $scannerUpload['original_name'] ?? 'scanned-letter.pdf',
                'application/pdf',
                null,
                true,
            );
        }
        if (count($originalLetters) > 10) {
            return back()->withErrors(['original_letters' => 'حداکثر ۱۰ فایل برای اصل نامه قابل ثبت است.'])->withInput();
        }

        if ($data['type'] === Correspondence::TYPE_INCOMING && count($originalLetters) === 0) {
            return back()->withErrors(['original_letters' => 'برای ثبت نامه وارده، تصویر یا PDF اصل نامه را بارگذاری کنید.'])->withInput();
        }

        $attachments = array_values(array_filter((array) $request->file('attachments', [])));
        if ($data['type'] !== Correspondence::TYPE_INCOMING && in_array($mode, ['file', 'both'], true) && count($attachments) === 0) {
            return back()->withErrors(['attachments' => 'برای روش انتخاب‌شده حداقل یک فایل پیوست کنید.'])->withInput();
        }

        try {
            $correspondence = DB::transaction(function () use ($data, $mode, $body, $originalLetters, $attachments, $service, $documents, $request): Correspondence {
                $correspondence = $service->createDraft([
                    'type' => $data['type'],
                    'classification' => $data['classification'] ?? Correspondence::CLASSIFICATION_ORDINARY,
                    'subject' => $data['subject'],
                    'body' => $mode === 'file' ? null : $body,
                    'sender_name' => $data['sender_name'] ?? null,
                    'recipient_name' => $data['recipient_name'] ?? null,
                    'received_at' => $data['received_at'] ?? null,
                    'sent_at' => $data['sent_at'] ?? null,
                    'created_department_id' => $data['created_department_id'] ?? null,
                    'recipient_department_id' => $data['recipient_department_id'] ?? null,
                    'external_organization_id' => $data['external_organization_id'] ?? null,
                    'external_department_id' => $data['external_department_id'] ?? null,
                    'reply_to_id' => $data['reply_to_id'] ?? null,
                ], $request->user());

                foreach ($originalLetters as $file) {
                    $documents->create(
                        data: [
                            'title' => Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 255, ''),
                            'document_type' => 'correspondence_original',
                        ],
                        file: $file,
                        creator: $request->user(),
                        linkable: $correspondence,
                        purpose: 'correspondence_original',
                    );
                }

                foreach ($attachments as $file) {
                    $documents->create(
                        data: [
                            'title' => Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 255, ''),
                            'document_type' => 'correspondence_attachment',
                        ],
                        file: $file,
                        creator: $request->user(),
                        linkable: $correspondence,
                        purpose: 'correspondence_attachment',
                    );
                }

                return $correspondence;
            });
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['subject' => $exception->getMessage()])->withInput();
        }

        try {
            app(CorrespondenceWorkflowService::class)->addCopies($correspondence, $data['copy_department_ids'] ?? [], $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['copy_department_ids' => $exception->getMessage()])->withInput();
        }

        if (is_array($scannerUpload)) {
            Storage::disk('local')->delete($scannerUpload['path']);
            \Illuminate\Support\Facades\Cache::forget($scannerUpload['key']);
        }

        return redirect()->route('correspondence.index')
            ->with('success', 'پیش‌نویس مکاتبه و پیوست‌های آن با موفقیت ذخیره شد.');
    }


    public function updateExternalTracking(
        Request $request,
        Correspondence $correspondence,
        CorrespondenceAccessService $access,
        CorrespondenceWorkflowService $workflow,
    ): RedirectResponse {
        if ($correspondence->type !== Correspondence::TYPE_OUTGOING) {
            return back()->withErrors([
                'external_tracking' => 'پیگیری ثبت سازمان مقصد فقط برای نامه‌های صادره قابل ثبت است.',
            ]);
        }

        $user = $request->user();
        $canUpdate = $user->isSystemAdmin()
            || $access->isSecretariat($user)
            || (int) $correspondence->created_by === (int) $user->id
            || (int) $correspondence->task?->assignee_id === (int) $user->id
            || (int) $correspondence->task?->created_by === (int) $user->id;

        abort_unless($canUpdate, 403);

        $data = $request->validate([
            'task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')],
            'external_registration_number' => ['nullable', 'string', 'max:100'],
            'external_registered_at' => ['nullable', 'date'],
            'dispatch_method' => ['nullable', 'string', 'max:50'],
            'external_tracking_notes' => ['nullable', 'string', 'max:5000'],
            'sent_at' => ['nullable', 'date'],
        ]);

        if (array_key_exists('sent_at', $data)) {
            abort_unless($access->isSecretariat($user) || $user->isSystemAdmin(), 403, 'ثبت ارسال نهایی فقط توسط دبیرخانه مجاز است.');
        }

        if (! $access->isSecretariat($user) && ! $user->isSystemAdmin() && ! empty($data['task_id'])) {
            $task = Task::query()->findOrFail($data['task_id']);
            abort_unless(
                (int) $task->created_by === (int) $user->id
                    || (int) $task->assignee_id === (int) $user->id,
                403,
            );
        }

        DB::transaction(function () use ($correspondence, $data, $user, $access, $workflow): void {
            $letter = Correspondence::query()->whereKey($correspondence->id)->lockForUpdate()->firstOrFail();
            $wasSent = $letter->sent_at !== null;
            $canUpdateLocked = $user->isSystemAdmin()
                || $access->isSecretariat($user)
                || (int) $letter->created_by === (int) $user->id
                || (int) $letter->task()->value('assignee_id') === (int) $user->id
                || (int) $letter->task()->value('created_by') === (int) $user->id;

            abort_unless($letter->type === Correspondence::TYPE_OUTGOING && $canUpdateLocked, 403);

            $letter->fill($data);
            $changedFields = array_keys($letter->getDirty());
            if ($changedFields === []) {
                return;
            }

            $letter->save();

            if (! $wasSent && $letter->sent_at !== null) {
                $workflow->onDispatched($letter, $user);
            }

            app(AuditServiceInterface::class)->record(
                'correspondence.external_tracking.updated',
                $letter,
                null,
                null,
                ['changed_fields' => $changedFields],
            );
        });

        return back()->with('success', 'اطلاعات پیگیری نامه صادره ذخیره شد.');
    }

    public function addCopies(
        Request $request,
        Correspondence $correspondence,
        CorrespondenceAccessService $access,
        CorrespondenceWorkflowService $workflow,
    ): RedirectResponse {
        abort_unless($access->canView($correspondence, $request->user()), 403);

        $currentDepartmentId = $correspondence->current_department_id;
        abort_unless(
            $correspondence->workflow_state !== 'completed_with_result'
                && $currentDepartmentId !== null
                && (int) Department::query()->whereKey($currentDepartmentId)->value('manager_id') === (int) $request->user()->id,
            403,
            'افزودن رونوشت در ادامه گردش فقط توسط مدیر واحد مسئول نامه مجاز است.',
        );

        $data = $request->validate([
            'department_ids' => ['required', 'array', 'min:1', 'max:20'],
            'department_ids.*' => ['required', 'integer', 'distinct', Rule::exists('departments', 'id')->where('is_active', true)],
        ]);

        try {
            $workflow->addCopies($correspondence, $data['department_ids'], $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['department_ids' => $exception->getMessage()])->withInput();
        }

        return back()->with('success', 'رونوشت‌های انتخاب‌شده به مکاتبه اضافه شدند.');
    }

    public function action(
        Request $request,
        Correspondence $correspondence,
        CorrespondenceAccessService $access,
        CorrespondenceWorkflowService $workflow,
    ): RedirectResponse {
        abort_unless($access->canView($correspondence, $request->user()), 403);

        $data = $request->validate([
            'action_type' => ['required', Rule::in([
                CorrespondenceAction::SUBMIT_RESULT,
                CorrespondenceAction::MANAGER_APPROVE,
                CorrespondenceAction::CONFIRM_RESULT,
                CorrespondenceAction::MANAGER_REFER,
                CorrespondenceAction::REQUEST_TASK,
                CorrespondenceAction::REQUEST_PROJECT,
                CorrespondenceAction::REQUEST_LETTER,
            ])],
            'note' => ['nullable', 'string', 'max:10000'],
            'to_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
        ]);

        try {
            $workflow->handleAction($correspondence, $request->user(), $data);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['workflow_action' => $exception->getMessage()])->withInput();
        }

        return back()->with('success', 'اقدام گردش مکاتبه ثبت شد.');
    }

    public function register(
        Request $request,
        Correspondence $correspondence,
        CorrespondenceNumberingService $numbering,
        CorrespondenceAccessService $access,
        CorrespondenceWorkflowService $workflow,
    ): RedirectResponse {
        abort_unless($access->canRegister($correspondence, $request->user()), 403);

        try {
            $numbering->register($correspondence, $request->user());
            $workflow->onRegistered($correspondence, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['registration' => $exception->getMessage()]);
        }

        return back()->with('success', 'مکاتبه با موفقیت ثبت نهایی شد.');
    }

    private function sanitizeBody(string $body): string
    {
        $allowedTags = '<p><div><br><strong><b><em><i><u><ul><ol><li><h2><h3><blockquote><table><thead><tbody><tr><th><td>';
        $body = strip_tags($body, $allowedTags);

        return (string) preg_replace_callback(
            '/<(\/?)(p|div|br|strong|b|em|i|u|ul|ol|li|h2|h3|blockquote|table|thead|tbody|tr|th|td)\b([^>]*)>/i',
            static function (array $match): string {
                $closing = $match[1];
                $tag = strtolower($match[2]);
                if ($closing === '/' || $tag === 'br') {
                    return '<'.$closing.$tag.'>';
                }
                $direction = preg_match('/\bdir\s*=\s*["\'](rtl|ltr)["\']/i', $match[3], $dirMatch)
                    ? strtolower($dirMatch[1])
                    : null;
                return '<'.$tag.($direction !== null ? ' dir="'.$direction.'"' : '').'>';
            },
            $body,
        );
    }
}
