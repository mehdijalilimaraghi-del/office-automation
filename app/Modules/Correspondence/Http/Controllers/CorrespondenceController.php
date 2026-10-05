<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Http\Controllers;

use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Services\CorrespondenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CorrespondenceController
{
    public function __construct(private readonly CorrespondenceService $service) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Correspondence/Index', [
            'correspondences' => $this->service->list($request->only(['type', 'status', 'search'])),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:incoming,outgoing,internal'],
            'status' => ['nullable', 'in:draft,registered'],
            'subject' => ['required', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            'sender_organization_id' => ['nullable', 'integer', 'exists:external_organizations,id'],
            'sender_department_id' => ['nullable', 'integer', 'exists:external_departments,id'],
            'sender_contact_id' => ['nullable', 'integer', 'exists:external_contacts,id'],
            'recipient_organization_id' => ['nullable', 'integer', 'exists:external_organizations,id'],
            'recipient_department_id' => ['nullable', 'integer', 'exists:external_departments,id'],
            'recipient_contact_id' => ['nullable', 'integer', 'exists:external_contacts,id'],
            'registered_at' => ['nullable', 'date'],
        ]);

        $this->service->create($data, $request->user());

        return to_route('correspondence.index')->with('success', 'مکاتبه با موفقیت ایجاد شد.');
    }

    public function register(Correspondence $correspondence): RedirectResponse
    {
        $this->service->register($correspondence);

        return back()->with('success', 'مکاتبه ثبت شد.');
    }

    public function refer(Request $request, Correspondence $correspondence): RedirectResponse
    {
        $data = $request->validate([
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
            'instruction' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->service->refer(
            $correspondence,
            User::query()->findOrFail($data['to_user_id']),
            $request->user(),
            $data['instruction'] ?? null,
        );

        return back()->with('success', 'مکاتبه ارجاع شد.');
    }
}