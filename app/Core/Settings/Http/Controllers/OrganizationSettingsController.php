<?php

namespace App\Core\Settings\Http\Controllers;

use App\Core\Settings\Services\SystemSettingsService;
use App\Core\Settings\Services\OrganizationDepartmentRoleService;
use App\Core\Departments\Models\Department;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\Settings\UpdateOrganizationSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
        'organization.stamp.path' => null,
        'organization.stamp.active' => '0',
        'correspondence.numbering.format' => 'year',
        'correspondence.numbering.custom_order' => 'year,department,sequence',
        'correspondence.numbering.separator' => '/',
        'correspondence.numbering.padding' => '6',
        'correspondence.registration_numbering.format' => 'simple',
        'correspondence.registration_numbering.custom_order' => 'year,department,sequence',
        'correspondence.registration_numbering.separator' => '/',
        'correspondence.registration_numbering.padding' => '6',
        'correspondence.composition.method' => 'both',
        'correspondence.composition.font_family' => 'Vazirmatn',
        'correspondence.approval.incoming' => 'approval',
        'correspondence.approval.outgoing' => 'approval',
        'correspondence.approval.internal' => 'direct',
        'projects.response_department.default' => null,
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
                'stamp_url' => $stored['organization.stamp.path'] ? route('settings.organization.stamp') : null,
                'stamp_active' => $stored['organization.stamp.active'] === '1',
                'numbering_format' => $stored['correspondence.numbering.format'],
                'numbering_custom_order' => $stored['correspondence.numbering.custom_order'],
                'numbering_separator' => $stored['correspondence.numbering.separator'],
                'numbering_padding' => $stored['correspondence.numbering.padding'],
                'registration_numbering_format' => $stored['correspondence.registration_numbering.format'],
                'registration_numbering_custom_order' => $stored['correspondence.registration_numbering.custom_order'],
                'registration_numbering_separator' => $stored['correspondence.registration_numbering.separator'],
                'registration_numbering_padding' => $stored['correspondence.registration_numbering.padding'],
                'composition_method' => $stored['correspondence.composition.method'],
                'correspondence_font_family' => $stored['correspondence.composition.font_family'],
                'approval_incoming' => $stored['correspondence.approval.incoming'],
                'approval_outgoing' => $stored['correspondence.approval.outgoing'],
                'approval_internal' => $stored['correspondence.approval.internal'],
                'management_department_id' => $roleSettings[OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::MANAGEMENT)],
                'project_management_department_id' => $roleSettings[OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::PROJECT_MANAGEMENT)],
                'secretariat_department_id' => $roleSettings[OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT)],
                'default_response_department_id' => $stored['projects.response_department.default'],
            ],
            'departments' => Department::query()
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id', 'icon', 'is_active']),
        ]);
    }

    public function update(
        UpdateOrganizationSettingsRequest $request,
    ): RedirectResponse {
        $data = $request->validated();
        unset($data['stamp'], $data['remove_stamp']);
        $previous = $this->settings->get(['organization.stamp.path' => null, 'organization.stamp.active' => '0']);
        $stampPath = $previous['organization.stamp.path'];
        if ($request->boolean('remove_stamp')) {
            $stampPath = null;
            $data['stamp_active'] = false;
        } elseif ($request->hasFile('stamp')) {
            $stampPath = $request->file('stamp')->store('organization/stamp', 'local');
        }
        $stampActive = (bool) ($data['stamp_active'] ?? ($previous['organization.stamp.active'] === '1')) && filled($stampPath);

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
            'organization.stamp.path' => $stampPath,
            'organization.stamp.active' => $stampActive ? '1' : '0',
            'correspondence.numbering.format' => $data['numbering_format'],
            'correspondence.numbering.custom_order' => implode(',', array_slice([$data['numbering_order_first'], $data['numbering_order_second'], $data['numbering_order_third'] ?? 'sequence'], 0, (int) ($data['numbering_custom_fields_count'] ?? 3))),
            'correspondence.numbering.separator' => $data['numbering_separator'],
            'correspondence.numbering.padding' => (string) $data['numbering_padding'],
            'correspondence.registration_numbering.format' => $data['registration_numbering_format'],
            'correspondence.registration_numbering.custom_order' => implode(',', array_slice([$data['registration_numbering_order_first'], $data['registration_numbering_order_second'], $data['registration_numbering_order_third'] ?? 'sequence'], 0, (int) ($data['registration_numbering_custom_fields_count'] ?? 3))),
            'correspondence.registration_numbering.separator' => $data['registration_numbering_separator'],
            'correspondence.registration_numbering.padding' => (string) $data['registration_numbering_padding'],
            'correspondence.composition.method' => $data['composition_method'],
            'correspondence.composition.font_family' => $data['correspondence_font_family'],
            'correspondence.approval.incoming' => $data['approval_incoming'],
            'correspondence.approval.outgoing' => $data['approval_outgoing'],
            'correspondence.approval.internal' => $data['approval_internal'],
            OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::MANAGEMENT) => $data['management_department_id'] ?? null,
            OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::PROJECT_MANAGEMENT) => $data['project_management_department_id'] ?? null,
            OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT) => $data['secretariat_department_id'] ?? null,
            'projects.response_department.default' => $data['default_response_department_id'] ?? null,
        ]);

        if ($previous['organization.stamp.path'] && $previous['organization.stamp.path'] !== $stampPath) {
            Storage::disk('local')->delete($previous['organization.stamp.path']);
        }

        return back()->with('success', 'تنظیمات سازمان با موفقیت ذخیره شد.');
    }

    public function stamp(Request $request): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);
        $stored = $this->settings->get(['organization.stamp.path' => null, 'organization.stamp.active' => '0']);
        abort_unless(filled($stored['organization.stamp.path']), 404);
        abort_unless(Storage::disk('local')->exists($stored['organization.stamp.path']), 404);

        return Storage::disk('local')->response($stored['organization.stamp.path'], null, [
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
