<?php

namespace App\Core\Settings\Http\Controllers;

use App\Core\Settings\Services\SystemSettingsService;
use App\Core\Settings\Services\OrganizationDepartmentRoleService;
use App\Core\Departments\Models\Department;
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
        $stored = $this->settings->get(self::KEYS);
        $roleSettings = $this->settings->get([
            OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::MANAGEMENT) => null,
            OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::PROJECT_MANAGEMENT) => null,
            OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT) => null,
        ]);

        return Inertia::render('Core/Settings/Organization', [
            'settings' => [
                'name' => $stored['organization.name'],
                'short_name' => $stored['organization.short_name'],
                'national_id' => $stored['organization.national_id'],
                'registration_number' => $stored['organization.registration_number'],
                'economic_code' => $stored['organization.economic_code'],
                'phone' => $stored['organization.phone'],
                'email' => $stored['organization.email'],
                'website' => $stored['organization.website'],
                'address' => $stored['organization.address'],
                'management_department_id' => $roleSettings[OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::MANAGEMENT)],
                'project_management_department_id' => $roleSettings[OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::PROJECT_MANAGEMENT)],
                'secretariat_department_id' => $roleSettings[OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT)],
            ],
            'departments' => Department::query()
                ->orderBy('name')
                ->get(['id', 'name', 'is_active']),
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
            OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::MANAGEMENT) => $data['management_department_id'] ?? null,
            OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::PROJECT_MANAGEMENT) => $data['project_management_department_id'] ?? null,
            OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT) => $data['secretariat_department_id'] ?? null,
        ]);

        return back()->with('success', 'تنظیمات سازمان با موفقیت ذخیره شد.');
    }
}
