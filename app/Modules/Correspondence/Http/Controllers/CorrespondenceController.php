<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Http\Controllers;

use App\Core\Departments\Models\Department;
use App\Http\Controllers\Controller;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Services\CorrespondenceNumberingService;
use App\Modules\Correspondence\Services\CorrespondenceAccessService;
use App\Modules\Correspondence\Services\CorrespondenceService;
use App\Modules\Documents\Services\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        ]);

        $query = $access->visibleToQuery(Correspondence::query(), $request->user())
            ->with(['creator:id,name', 'department:id,name', 'registrar:id,name'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('subject', 'like', '%'.$search.'%')
                        ->orWhere('registration_number', 'like', '%'.$search.'%')
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
            ],
            'canCreate' => $request->user()->hasPermission('correspondence.create'),
        ]);
    }

    public function show(Request $request, Correspondence $correspondence, CorrespondenceAccessService $access): Response
    {
        abort_unless($access->canView($correspondence, $request->user()), 403);

        $correspondence->load([
            'creator:id,name',
            'registrar:id,name',
            'department:id,name',
            'replyTo:id,subject,registration_number,type,status',
            'replies:id,subject,registration_number,type,status,reply_to_id',
            'documents.document.latestVersion',
        ]);

        return Inertia::render('Correspondence/Show', [
            'correspondence' => [
                'id' => $correspondence->id,
                'type' => $correspondence->type,
                'classification' => $correspondence->classification,
                'subject' => $correspondence->subject,
                'body' => $correspondence->body,
                'status' => $correspondence->status,
                'registration_number' => $correspondence->registration_number,
                'registered_at' => $correspondence->registered_at,
                'created_at' => $correspondence->created_at,
                'sender_name' => $correspondence->sender_name,
                'recipient_name' => $correspondence->recipient_name,
                'received_at' => $correspondence->received_at,
                'sent_at' => $correspondence->sent_at,
                'creator' => $correspondence->creator,
                'registrar' => $correspondence->registrar,
                'department' => $correspondence->department,
                'reply_to' => $correspondence->replyTo !== null && $access->canView($correspondence->replyTo, $request->user())
                    ? $correspondence->replyTo
                    : null,
                'replies' => $correspondence->replies
                    ->filter(fn (Correspondence $reply) => $access->canView($reply, $request->user()))
                    ->values(),
            ],
            'attachments' => $correspondence->documents
                ->filter(fn ($link) => $link->document !== null && $link->document->latestVersion !== null)
                ->map(fn ($link) => [
                    'id' => $link->document->id,
                    'title' => $link->document->title,
                    'file_name' => $link->document->latestVersion->original_name,
                    'size' => $link->document->latestVersion->size,
                ])
                ->values(),
            'canRegister' => $access->canRegister($correspondence, $request->user()),
        ]);
    }

    public function create(Request $request, CorrespondenceAccessService $access): Response
    {
        $types = array_values(array_filter([
            ['value' => Correspondence::TYPE_INCOMING, 'label' => 'وارده'],
            ['value' => Correspondence::TYPE_OUTGOING, 'label' => 'صادره'],
            ['value' => Correspondence::TYPE_INTERNAL, 'label' => 'داخلی'],
        ], fn (array $type): bool => $access->canCreateType($type['value'], $request->user())));

        return Inertia::render('Correspondence/Create', [
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
                ->get(['id', 'registration_number', 'subject']),
        ]);
    }

    public function store(
        Request $request,
        CorrespondenceService $service,
        DocumentService $documents,
        CorrespondenceAccessService $access,
    ): RedirectResponse {
        $data = $request->validate([
            'type' => ['required', Rule::in(Correspondence::allowedTypes())],
            'classification' => ['sometimes', 'required', Rule::in([Correspondence::CLASSIFICATION_ORDINARY, Correspondence::CLASSIFICATION_CONFIDENTIAL])],
            'subject' => ['required', 'string', 'max:255'],
            'composition_mode' => ['required', Rule::in(['editor', 'file', 'both'])],
            'body' => ['nullable', 'string', 'max:100000'],
            'sender_name' => ['nullable', 'string', 'max:255'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'received_at' => ['nullable', 'date'],
            'sent_at' => ['nullable', 'date'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'reply_to_id' => ['nullable', 'integer', Rule::exists('correspondences', 'id')->where('status', Correspondence::STATUS_REGISTERED)],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => [
                'file',
                'max:20480',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,txt,csv,zip',
            ],
        ]);

        abort_unless($access->canCreateType($data['type'], $request->user()), 403);

        $mode = $data['composition_mode'];
        $body = $this->sanitizeBody((string) ($data['body'] ?? ''));

        if (in_array($mode, ['editor', 'both'], true) && trim(strip_tags($body)) === '') {
            return back()->withErrors(['body' => 'متن نامه را وارد کنید.'])->withInput();
        }

        $attachments = array_values(array_filter((array) $request->file('attachments', [])));
        if (in_array($mode, ['file', 'both'], true) && count($attachments) === 0) {
            return back()->withErrors(['attachments' => 'برای روش انتخاب‌شده حداقل یک فایل پیوست کنید.'])->withInput();
        }

        try {
            $correspondence = DB::transaction(function () use ($data, $mode, $body, $attachments, $service, $documents, $request): Correspondence {
                $correspondence = $service->createDraft([
                    'type' => $data['type'],
                    'classification' => $data['classification'] ?? Correspondence::CLASSIFICATION_ORDINARY,
                    'subject' => $data['subject'],
                    'body' => $mode === 'file' ? null : $body,
                    'sender_name' => $data['sender_name'] ?? null,
                    'recipient_name' => $data['recipient_name'] ?? null,
                    'received_at' => $data['received_at'] ?? null,
                    'sent_at' => $data['sent_at'] ?? null,
                    'department_id' => $data['department_id'] ?? null,
                    'reply_to_id' => $data['reply_to_id'] ?? null,
                ], $request->user());

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

        return redirect()->route('correspondence.index')
            ->with('success', 'پیش‌نویس مکاتبه و پیوست‌های آن با موفقیت ذخیره شد.');
    }

    public function register(
        Request $request,
        Correspondence $correspondence,
        CorrespondenceNumberingService $numbering,
        CorrespondenceAccessService $access,
    ): RedirectResponse {
        abort_unless($access->canRegister($correspondence, $request->user()), 403);

        try {
            $numbering->register($correspondence, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['registration' => $exception->getMessage()]);
        }

        return back()->with('success', 'مکاتبه با موفقیت ثبت نهایی شد.');
    }

    private function sanitizeBody(string $body): string
    {
        $allowedTags = '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><blockquote><table><thead><tbody><tr><th><td>';
        $body = strip_tags($body, $allowedTags);

        return (string) preg_replace_callback(
            '/<(\/?)(p|br|strong|b|em|i|u|ul|ol|li|h2|h3|blockquote|table|thead|tbody|tr|th|td)\b[^>]*>/i',
            static fn (array $match): string => '<'.$match[1].strtolower($match[2]).'>',
            $body,
        );
    }
}
