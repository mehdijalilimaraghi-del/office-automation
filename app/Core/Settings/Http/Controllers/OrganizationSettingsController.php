<?php

namespace App\Core\Settings\Http\Controllers;

use App\Core\Settings\Services\SystemSettingsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\Settings\UpdateOrganizationSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationSettingsController extends Controller
{
    private const KEYS = [
        'organization.name' => 'نام سازمان',
        'organization.short_name' => null,
        'organization.national_id' => null,
        'organization.registration_number' => null,
        'organization.economic_code' => null,
        'organization.phone' => null,
        'organization.email' => null,
        'organization.website' => null,
        'organization.address' => null,
    ];

    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    public function edit(): Response
    {
        return Inertia::render('Core/Settings/Organization', [
            'settings' => $this->settings->get(self::KEYS),
        ]);
    }

    public function update(
        UpdateOrganizationSettingsRequest $request,
    ): RedirectResponse {
        $data = $request->validated();

        $this->settings->set([
            'organization.name' => $data['name'],
            'organization.short_name' => $data['short_name'] ?? null,
            'organization.national_id' => $data['national_id'] ?? null,
            'organization.registration_number' => $data['registration_number'] ?? null,
            'organization.economic_code' => $data['economic_code'] ?? null,
            'organization.phone' => $data['phone'] ?? null,
            'organization.email' => $data['email'] ?? null,
            'organization.website' => $data['website'] ?? null,
            'organization.address' => $data['address'] ?? null,
        ]);

        return back()->with('success', 'تنظیمات سازمان با موفقیت ذخیره شد.');
    }
}
