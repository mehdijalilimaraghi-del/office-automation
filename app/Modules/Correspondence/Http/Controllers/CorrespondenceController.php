<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Http\Controllers;

use App\Core\Departments\Models\Department;
use App\Http\Controllers\Controller;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Services\CorrespondenceNumberingService;
use App\Modules\Correspondence\Services\CorrespondenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request, CorrespondenceService $service): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(Correspondence::allowedTypes())],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:100000'],
            'sender_name' => ['nullable', 'string', 'max:255'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'received_at' => ['nullable', 'date'],
            'sent_at' => ['nullable', 'date'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'reply_to_id' => ['nullable', 'integer', Rule::exists('correspondences', 'id')->where('status', Correspondence::STATUS_REGISTERED)],
        ]);

        try {
            $correspondence = $service->createDraft($data, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['subject' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('correspondence.index')
            ->with('success', 'پیش‌نویس مکاتبه با موفقیت ایجاد شد.');
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
}
