<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers;

use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Services\ArchiveApprovalPolicy;
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
    public function index(Request $request, ArchiveApprovalPolicy $approval, ArchiveLocationCatalog $locations): Response
    {
        Gate::authorize('viewAny', Document::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::in(['digital', 'physical'])],
        ]);

        $userId = $request->user()->id;
        $records = Document::query()
            ->select(['id', 'title', 'document_type', 'archive_kind', 'archive_location', 'retain_until'])
            ->where('is_active', true)
            ->whereNotNull('archive_kind')
            ->where(function (Builder $query) use ($userId): void {
                $query->where('created_by', $userId)
                    ->orWhereHas('accesses', fn (Builder $access) => $access->where('user_id', $userId));
            })
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $pattern = '%'.str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $filters['search']).'%';
                $query->where(function (Builder $query) use ($pattern): void {
                    $query->where('title', 'like', $pattern)
                        ->orWhere('document_type', 'like', $pattern)
                        ->orWhere('description', 'like', $pattern);
                });
            })
            ->when($filters['kind'] ?? null, fn (Builder $query, string $kind) => $query->where('archive_kind', $kind))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Archive/Index', [
            'records' => $records,
            'filters' => $filters,
            'locations' => $locations->paths(),
            'canCreate' => Gate::allows('create', Document::class),
            'approvalExceptions' => $request->user()->hasPermission('settings.manage') ? $approval->exceptions() : null,
        ]);
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
            'record' => $document->only(['id', 'title', 'description', 'document_type', 'archive_kind', 'archive_location']),
            'locations' => $locations->paths(),
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
        if ((int) $document->created_by === (int) $recipient->id || ! $recipient->hasPermission('documents.view')) {
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
            $locations->validatePath($data['location'], 'location');
        }
        $custody->record($document, $request->user(), $data);

        return back()->with('success', 'اقدام بایگانی ثبت شد.');
    }

    private function authorizeArchiveDocument(Request $request, Document $document): void
    {
        Gate::authorize('viewAny', Document::class);
        abort_if($document->archive_kind === null, 404);
        abort_unless((int) $document->created_by === (int) $request->user()->id
            || $document->accesses()->where('user_id', $request->user()->id)->exists(), 403);
        Gate::authorize('view', $document);
    }

    private function canUpdate(Request $request, Document $document): bool
    {
        return Gate::allows('update', $document) && (
            (int) $document->created_by === (int) $request->user()->id
            || $document->accesses()->where('user_id', $request->user()->id)->where('access_level', 'edit')->exists()
        );
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
        Gate::authorize('viewAny', Document::class);
        Gate::authorize('create', Document::class);
    }

    public function store(Request $request, DocumentService $documents, ScannerStagingService $scanner, ArchiveLocationCatalog $locations): RedirectResponse
    {
        Gate::authorize('viewAny', Document::class);
        Gate::authorize('create', Document::class);

        $fileTypes = $this->fileTypes($request->string('archive_kind')->toString());

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'archive_kind' => ['required', Rule::in(['digital', 'physical'])],
            'archive_location' => ['exclude_unless:archive_kind,physical', 'required', 'string', 'max:255'],
            'retain_until' => ['nullable', 'date_format:Y-m-d'],
            'scanner_upload_token' => ['nullable', 'string', 'size:64', 'alpha_num:ascii', 'prohibits:file'],
            'file' => ['required_without:scanner_upload_token', 'nullable', 'file', 'max:20480', $fileTypes],
        ]);

        if ($data['archive_kind'] === 'physical') {
            $locations->validatePath($data['archive_location'], 'archive_location');
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

        return redirect()->route('archive.index')->with('success', 'مدرک در آرشیو ثبت شد.');
    }
}
