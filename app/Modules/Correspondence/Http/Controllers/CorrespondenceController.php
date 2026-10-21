<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Http\Controllers;

use App\Core\Departments\Models\Department;
use App\Http\Controllers\Controller;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Services\CorrespondenceNumberingService;
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
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'type' => ['nullable', Rule::in(Correspondence::allowedTypes())],
            'status' => ['nullable', Rule::in([Correspondence::STATUS_DRAFT, Correspondence::STATUS_REGISTERED])],
        ]);

        $query = Correspondence::query()
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

        return Inertia::render('Correspondence/Index', [
            'correspondences' => $query->paginate(15)->withQueryString(),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'type' => $filters['type'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'canCreate' => $request->user()->hasPermission('correspondence.create'),
            'canRegister' => $request->user()->hasPermission('correspondence.register'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Correspondence/Create', [
            'types' => [
                ['value' => Correspondence::TYPE_INCOMING, 'label' => 'وارده'],
                ['value' => Correspondence::TYPE_OUTGOING, 'label' => 'صادره'],
                ['value' => Correspondence::TYPE_INTERNAL, 'label' => 'داخلی'],
            ],
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'repliesTo' => Correspondence::query()
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
    ): RedirectResponse {
        $data = $request->validate([
            'type' => ['required', Rule::in(Correspondence::allowedTypes())],
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

        $mode = $data['composition_mode'];
        $body = $this->sanitizeBody((string) ($data['body'] ?? ''));

        if (in_array($mode, ['editor', 'both'], true) && trim(strip_tags($body)) === '') {
            return back()->withErrors(['body' => 'متن نامه را وارد کنید.'])->withInput();
        }

        $attachments = $request->file('attachments', []);
        if (in_array($mode, ['file', 'both'], true) && count($attachments) === 0) {
            return back()->withErrors(['attachments' => 'برای روش انتخاب‌شده حداقل یک فایل پیوست کنید.'])->withInput();
        }

        try {
            $correspondence = DB::transaction(function () use ($data, $mode, $body, $attachments, $service, $documents, $request): Correspondence {
                $correspondence = $service->createDraft([
                    'type' => $data['type'],
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
    ): RedirectResponse {
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
