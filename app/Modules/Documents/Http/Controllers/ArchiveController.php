<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers;

use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Services\ArchiveApprovalPolicy;
use App\Modules\Documents\Services\ArchiveAudienceService;
use App\Core\Settings\Services\OrganizationDepartmentRoleService;
use App\Core\Departments\Models\Department;
use App\Core\Roles\Models\Role;
use Illuminate\Support\Facades\DB;
use App\Modules\Documents\Services\ArchiveCustodyService;
use App\Modules\Documents\Services\ArchiveLocationCatalog;
use App\Modules\Documents\Services\ArchiveReferenceService;
use App\Modules\Documents\Services\DocumentService;
use App\Modules\Documents\Services\ScannerStagingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ArchiveController
{
    public function __construct(private readonly \App\Modules\Documents\Services\ArchiveAccessService $access) {}
    public function index(Request $request, ArchiveApprovalPolicy $approval, ArchiveLocationCatalog $locations): Response
    {
        abort_unless($this->access->canEnter($request->user()), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::in(['digital', 'physical'])],
            'category' => ['nullable', 'string', 'max:100'],
            'access' => ['nullable', Rule::in(['mine', 'shared'])],
        ]);

        $central = $this->centralArchive($request);
        $centralMode = $request->routeIs('archive.central.index');
        abort_if($centralMode && $central === null, 404);
        $userId = $request->user()->id;
        $categories = $this->access->visible(Document::query()->whereNotNull('archive_kind')->where('is_active', true), $request->user())->distinct()->orderBy('document_type')->pluck('document_type');
        $records = Document::query()
            ->select(['id', 'title', 'document_type', 'archive_kind', 'archive_location', 'retain_until', 'archive_restricted', 'archive_visibility', 'archive_department_id'])
            ->where('is_active', true)
            ->whereNotNull('archive_kind')
            ->when($centralMode, fn (Builder $query) => $query->where('archive_department_id', $central['id']))
            ->tap(fn (Builder $query) => $this->access->visible($query, $request->user()))
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $pattern = '%'.str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $filters['search']).'%';
                $query->where(function (Builder $query) use ($pattern): void {
                    $query->where('title', 'like', $pattern)
                        ->orWhere('document_type', 'like', $pattern)
                        ->orWhere('description', 'like', $pattern);
                });
            })
            ->when($filters['kind'] ?? null, fn (Builder $query, string $kind) => $query->where('archive_kind', $kind))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('document_type', $category))
            ->when(($filters['access'] ?? null) === 'mine', fn (Builder $query) => $query->where('created_by', $userId))
            ->when(($filters['access'] ?? null) === 'shared', fn (Builder $query) => $query->whereHas('accesses', fn (Builder $access) => $access->where('user_id', $userId)))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Archive/Index', [
            'records' => $records,
            'filters' => $filters,
            'categories' => $categories,
            'centralArchive' => $central,
            'centralMode' => $centralMode,
            'locations' => $request->user()->hasPermission('settings.manage') ? $locations->paths() : [],
            'canCreate' => $this->access->departments($request->user())->contains(
                fn ($department) => (int) $department->id !== ($central['id'] ?? null)
            ),
            'approvalExceptions' => $request->user()->hasPermission('settings.manage') ? $approval->exceptions() : null,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeRegistration();
        $centralMode = $request->routeIs('archive.central.create');
        $central = $this->centralArchive($request);
        if ($centralMode) {
            abort_unless($central !== null && $central['canFile'], 403);
        }
        $departments = $this->access->departments($request->user())
            ->filter(fn ($department) => $centralMode
                ? (int) $department->id === $central['id']
                : (int) $department->id !== ($central['id'] ?? null))->values();
        $settings = \App\Modules\Documents\Models\DepartmentArchiveSetting::query()
            ->whereIn('department_id', $departments->pluck('id'))->get()->keyBy('department_id');

        return Inertia::render('Archive/Create', [
            'departments' => $departments->map(fn ($department) => [
                'id' => $department->id, 'name' => $department->name,
                'categories' => $settings->get($department->id)?->categories ?? [],
                'locations' => array_map(static fn (array $location): string => implode(' / ', array_filter($location, fn ($part) => filled($part))), $settings->get($department->id)?->locations ?? []),
            ]),
            'relatedDepartments' => \App\Core\Departments\Models\Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'centralMode' => $centralMode,
            'audienceDepartments' => Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'audienceRoles' => Role::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'audienceManagers' => $departments->filter(fn ($department) => $this->access->manages($request->user(), (int) $department->id))->pluck('id')->values(),
            'centralDepartmentId' => app(\App\Core\Settings\Services\OrganizationDepartmentRoleService::class)
                ->activeDepartmentId(\App\Core\Settings\Services\OrganizationDepartmentRoleService::CENTRAL_ARCHIVE),
        ]);
    }

    private function centralArchive(Request $request): ?array
    {
        $id = app(OrganizationDepartmentRoleService::class)->activeDepartmentId(OrganizationDepartmentRoleService::CENTRAL_ARCHIVE);
        if ($id === null) {
            return null;
        }

        return [
            'id' => $id,
            'name' => Department::query()->whereKey($id)->value('name'),
            'canFile' => $this->access->departments($request->user())->contains('id', $id),
            'canManage' => $this->access->manages($request->user(), $id) || $request->user()->isSystemAdmin(),
        ];
    }

    public function updateVisibility(Request $request, Document $document, ArchiveAudienceService $audiences): RedirectResponse
    {
        $this->authorizeArchiveDocument($request, $document);
        Gate::authorize('manageAccess', $document);
        $data = $request->validate($audiences->rules(requireVisibility: true));
        $audiences->apply($document, $data);

        if (! $this->access->canView($request->user(), $document)) {
            return redirect()->route('archive.index')->with('success', 'دامنه مشاهده سند ذخیره شد.');
        }

        return back()->with('success', 'دامنه مشاهده سند ذخیره شد.');
    }

    public function updateLocationCatalog(Request $request, ArchiveLocationCatalog $locations): RedirectResponse
    {
        Gate::authorize('viewAny', Document::class);
        $data = $request->validate(['locations' => ['nullable', 'string', 'max:10000']]);
        $locations->save($data['locations'] ?? '');

        return back()->with('success', 'فهرست محل‌های بایگانی ذخیره شد.');
    }

    public function addReference(Request $request, Document $document, ArchiveReferenceService $references): RedirectResponse
    {
        $this->authorizeArchiveDocument($request, $document);
        abort_unless($this->canUpdate($request, $document), 403);
        $data = $request->validate([
            'target_type' => ['required', Rule::in(['correspondence', 'task', 'workflow'])],
            'target_id' => ['required', 'integer', 'min:1'],
        ]);
        $references->add($document, $request->user(), $data['target_type'], (int) $data['target_id']);

        return back()->with('success', 'پیوند مدرک ثبت شد.');
    }

    public function updateApprovalPolicy(Request $request, ArchiveApprovalPolicy $approval): RedirectResponse
    {
        Gate::authorize('viewAny', Document::class);
        $validated = $request->validate(['categories' => ['nullable', 'string', 'max:5000']]);
        $categories = preg_split('/\R/u', $validated['categories'] ?? '') ?: [];
        validator(['categories' => $categories], ['categories' => ['array', 'max:50'], 'categories.*' => ['max:100']])
            ->validate();

        $approval->save($categories);

        return back()->with('success', 'سیاست تأیید خروج ذخیره شد.');
    }

    public function show(Request $request, Document $document, ArchiveReferenceService $references, ArchiveLocationCatalog $locations): Response
    {
        $this->authorizeArchiveDocument($request, $document);

        $canManageAccess = Gate::allows('manageAccess', $document);

        return Inertia::render('Archive/Show', [
            'accessManagement' => $canManageAccess ? [
                'grants' => $document->accesses()->with('user:id,name')->orderBy('id')->get(['id', 'user_id', 'access_level']),
                'users' => User::query()->where('status', 'active')->whereKeyNot($document->created_by)
                    ->orderBy('name')->get(['id', 'name']),
            ] : null,
            'visibilityManagement' => $canManageAccess ? [
                'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'roles' => Role::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'selectedDepartments' => Department::query()->where('is_active', true)->whereIn('id', DB::table('archive_audiences')->where('document_id', $document->id)->where('target_type', 'department')->select('target_id'))->pluck('id'),
                'selectedRoles' => Role::query()->where('is_active', true)->whereIn('id', DB::table('archive_audiences')->where('document_id', $document->id)->where('target_type', 'role')->select('target_id'))->pluck('id'),
            ] : null,
            'record' => [
                ...$document->only(['id', 'title', 'description', 'document_type', 'archive_kind', 'archive_location']),
                'archive_visibility' => $document->archive_visibility ?? ($document->archive_restricted ? 'restricted' : 'department'),
                'central' => $document->archive_department_id !== null && $document->archive_department_id === ($this->centralArchive($request)['id'] ?? null),
            ],
            'locations' => $document->archive_department_id === null ? $locations->paths() : array_map(
                static fn (array $location): string => implode(' / ', array_filter($location, fn ($part) => filled($part))),
                \App\Modules\Documents\Models\DepartmentArchiveSetting::query()->where('department_id', $document->archive_department_id)->first()?->locations ?? [],
            ),
            'references' => $references->visible($document, $request->user()),
            'versions' => $document->versions()->orderByDesc('version_number')
                ->get(['version_number', 'original_name', 'created_at']),
            'custody' => $document->archive_kind === 'physical'
                ? $document->custodyEvents()->with('actor:id,name')->latest('id')->paginate(25)->withQueryString()
                : null,
            'custodyCurrent' => $document->archive_kind === 'physical'
                ? $document->custodyEvents()->latest('id')->first(['action', 'recipient', 'due_on', 'approval_required'])
                : null,
            'canUpdate' => $this->canUpdate($request, $document),
        ]);
    }

    public function updateAccess(Request $request, Document $document, DocumentService $documents): RedirectResponse
    {
        $this->authorizeArchiveDocument($request, $document);
        Gate::authorize('manageAccess', $document);
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
            'access_level' => ['required', Rule::in(['view', 'edit'])],
        ]);
        $recipient = User::query()->findOrFail($data['user_id']);
        if ((int) $document->created_by === (int) $recipient->id || ! $this->access->canEnter($recipient)) {
            throw ValidationException::withMessages(['user_id' => 'کاربر باید مجوز مشاهده اسناد داشته باشد و مالک مدرک نباشد.']);
        }
        if ($data['access_level'] === 'edit' && ! $recipient->hasPermission('documents.update')) {
            throw ValidationException::withMessages(['access_level' => 'برای واگذاری ویرایش، کاربر باید مجوز ویرایش اسناد داشته باشد.']);
        }

        $documents->grantAccess($document, $recipient, $request->user(), $data['access_level']);

        return back()->with('success', 'دسترسی مدرک ذخیره شد.');
    }

    public function revokeAccess(Request $request, Document $document, User $user): RedirectResponse
    {
        $this->authorizeArchiveDocument($request, $document);
        Gate::authorize('manageAccess', $document);
        $document->accesses()->where('user_id', $user->id)->delete();

        return back()->with('success', 'دسترسی کاربر به مدرک لغو شد.');
    }

    public function addVersion(Request $request, Document $document, DocumentService $documents): RedirectResponse
    {
        $this->authorizeArchiveDocument($request, $document);
        abort_unless($this->canUpdate($request, $document), 403);

        $request->validate(['file' => ['required', 'file', 'max:20480', $this->fileTypes($document->archive_kind)]]);
        $documents->addVersion($document, $request->file('file'), $request->user());

        return back()->with('success', 'نسخه جدید مدرک ذخیره شد.');
    }

    public function recordCustody(Request $request, Document $document, ArchiveCustodyService $custody, ArchiveLocationCatalog $locations): RedirectResponse
    {
        $this->authorizeArchiveDocument($request, $document);
        abort_unless($this->canUpdate($request, $document), 403);

        $data = $request->validate([
            'action' => ['required', Rule::in(['move', 'request', 'approve', 'reject', 'checkout', 'return'])],
            'location' => ['exclude_unless:action,move,return', 'required', 'string', 'max:255'],
            'recipient' => ['exclude_unless:action,request', 'required', 'string', 'max:255'],
            'due_on' => ['exclude_unless:action,request', 'required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'note' => ['nullable', 'required_if:action,request,reject', 'string', 'max:500'],
        ]);
        if (array_key_exists('location', $data)) {
            if ($document->archive_department_id !== null) {
                $setting = \App\Modules\Documents\Models\DepartmentArchiveSetting::query()->where('department_id', $document->archive_department_id)->first();
                $paths = array_map(static fn (array $location): string => implode(' / ', array_filter($location, fn ($part) => filled($part))), $setting?->locations ?? []);
                if (! in_array($data['location'], $paths, true)) {
                    throw ValidationException::withMessages(['location' => 'محل نگهداری را از فهرست بایگانی واحد انتخاب کنید.']);
                }
            } else {
                $locations->validatePath($data['location'], 'location');
            }
        }
        $custody->record($document, $request->user(), $data);

        return back()->with('success', 'اقدام بایگانی ثبت شد.');
    }

    private function authorizeArchiveDocument(Request $request, Document $document): void
    {
        abort_unless($this->access->canEnter($request->user()), 403);
        abort_if($document->archive_kind === null, 404);
        abort_unless($this->access->canView($request->user(), $document), 403);
        Gate::authorize('view', $document);
    }

    private function canUpdate(Request $request, Document $document): bool
    {
        return $this->access->canEdit($request->user(), $document);
    }

    private function fileTypes(?string $kind): string
    {
        return $kind === 'physical'
            ? 'mimes:pdf,jpg,jpeg,png,tif,tiff'
            : 'mimes:pdf,jpg,jpeg,png,tif,tiff,doc,docx,xls,xlsx';
    }

    public function startScan(Request $request, ScannerStagingService $scanner): JsonResponse
    {
        $this->authorizeRegistration();

        return $scanner->start($request, 'archive');
    }

    public function scanStatus(Request $request, ScannerStagingService $scanner, string $token): JsonResponse
    {
        $this->authorizeRegistration();

        return $scanner->status($request, 'archive', $token);
    }

    public function scanPreview(Request $request, ScannerStagingService $scanner, string $token): BinaryFileResponse
    {
        $this->authorizeRegistration();

        return $scanner->preview($request, 'archive', $token);
    }

    private function authorizeRegistration(): void
    {
        $user = request()->user();
        abort_unless($this->access->canEnter($user) && ($user->hasPermission('documents.create') || $this->access->departments($user)->isNotEmpty()), 403);
    }

    public function store(Request $request, DocumentService $documents, ScannerStagingService $scanner, ArchiveLocationCatalog $locations, ArchiveAudienceService $audiences): RedirectResponse
    {
        $this->authorizeRegistration();

        $fileTypes = $this->fileTypes($request->string('archive_kind')->toString());

        $data = $request->validate([
            ...$audiences->rules(),
            'archive_department_id' => ['nullable', 'integer'],
            'related_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'archive_restricted' => ['sometimes', 'boolean'],
            'title' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'archive_kind' => ['required', Rule::in(['digital', 'physical'])],
            'archive_location' => ['exclude_unless:archive_kind,physical', 'required', 'string', 'max:255'],
            'retain_until' => ['nullable', 'date_format:Y-m-d'],
            'scanner_upload_token' => ['nullable', 'string', 'size:64', 'alpha_num:ascii', 'prohibits:file'],
            'file' => ['required_without:scanner_upload_token', 'nullable', 'file', 'max:20480', $fileTypes],
        ]);

        $departmentId = $data['archive_department_id'] ?? null;
        if ($departmentId !== null) {
            abort_unless($this->access->departments($request->user())->contains('id', $departmentId), 403);
            $centralId = app(\App\Core\Settings\Services\OrganizationDepartmentRoleService::class)
                ->activeDepartmentId(\App\Core\Settings\Services\OrganizationDepartmentRoleService::CENTRAL_ARCHIVE);
            if ((int) $departmentId !== $centralId) {
                $data['related_department_id'] = $departmentId;
            } elseif (empty($data['related_department_id'])) {
                throw ValidationException::withMessages(['related_department_id' => 'واحد مرتبط با سند را انتخاب کنید.']);
            }
            $setting = \App\Modules\Documents\Models\DepartmentArchiveSetting::query()->where('department_id', $departmentId)->first();
            $categoryNames = array_column($setting?->categories ?? [], 'name');
            if (! in_array($data['document_type'], $categoryNames, true)) {
                throw ValidationException::withMessages(['document_type' => 'دسته سند باید در تنظیمات بایگانی همین واحد تعریف شده باشد.']);
            }
            $data['archive_restricted'] = $data['archive_restricted'] ?? false;
            if ($data['archive_kind'] === 'physical') {
                $paths = array_map(static fn (array $location): string => implode(' / ', array_filter($location, fn ($part) => filled($part))), $setting?->locations ?? []);
                if (! in_array($data['archive_location'], $paths, true)) {
                    throw ValidationException::withMessages(['archive_location' => 'محل نگهداری را از فهرست همین واحد انتخاب کنید.']);
                }
            }
        } elseif (! $request->user()->hasPermission('documents.create')) {
            throw ValidationException::withMessages(['archive_department_id' => 'واحد نگهدارنده را انتخاب کنید.']);
        }

        if ($departmentId === null && $data['archive_kind'] === 'physical') {
            $locations->validatePath($data['archive_location'], 'archive_location');
        }

        if ($departmentId !== null) {
            $visibility = $data['archive_visibility'] ?? (($data['archive_restricted'] ?? false) ? 'restricted' : 'department');
            if (in_array($visibility, ['organization', 'selected'], true)) {
                abort_unless($this->access->manages($request->user(), (int) $departmentId), 403);
            }
            $data['archive_visibility'] = $visibility;
        } elseif (isset($data['archive_visibility'])) {
            abort_unless($request->user()->hasPermission('documents.manage_access'), 403);
        }

        $token = $data['scanner_upload_token'] ?? null;
        if ($token === null) {
            $documents->create($data, $request->file('file'), $request->user());
        } else {
            $lock = Cache::lock('archive_scan_registration:'.hash('sha256', $token), 120);
            if (! $lock->get()) {
                throw ValidationException::withMessages(['scanner_upload_token' => 'ثبت این اسکن در حال انجام است.']);
            }

            try {
                $scan = $scanner->readyUpload('archive', $token, $request->user());
                if ($scan === null) {
                    throw ValidationException::withMessages(['scanner_upload_token' => 'اسکن معتبر نیست یا مهلت آن پایان یافته است.']);
                }
                $file = new UploadedFile(
                    Storage::disk('local')->path($scan['path']),
                    $scan['original_name'],
                    'application/pdf',
                    null,
                    true,
                );
                $documents->create($data, $file, $request->user());
                $scanner->discard($scan);
            } finally {
                $lock->release();
            }
        }

        $destination = isset($centralId) && (int) $departmentId === $centralId ? 'archive.central.index' : 'archive.index';

        return redirect()->route($destination)->with('success', 'مدرک در آرشیو ثبت شد.');
    }
}
