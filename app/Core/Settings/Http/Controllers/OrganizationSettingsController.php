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
        'organization.postal_code' => null,
        'organization.stamp.path' => null,
        'organization.stamp.active' => '0',
        'organization.letterhead.path' => null,
        'correspondence.numbering.format' => 'template',
        'correspondence.numbering.template' => '{{PYY}}/{{Dept}}/{{Num-6-r}}',
        'correspondence.registration_numbering.format' => 'template',
        'correspondence.registration_numbering.template' => '{{PYY}}/{{Num-6-r}}',
        'correspondence.composition.method' => 'both',
        'correspondence.composition.font_family' => 'Vazirmatn',
        'correspondence.composition.font_color' => '#202124',
        'correspondence.composition.font_size' => '12',
        'correspondence.composition.table_border_color' => '#000000',
        'correspondence.composition.shape_border_color' => '#000000',
        'correspondence.composition.page_size' => 'A4',
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
                'postal_code' => $stored['organization.postal_code'],
                'stamp_url' => $stored['organization.stamp.path'] ? route('settings.organization.stamp') : null,
                'letterhead_url' => $stored['organization.letterhead.path'] ? route('settings.organization.letterhead') : null,
                'stamp_active' => $stored['organization.stamp.active'] === '1',
                'numbering_template' => $stored['correspondence.numbering.template'],
                'registration_numbering_template' => $stored['correspondence.registration_numbering.template'],
                'composition_method' => $stored['correspondence.composition.method'],
                'correspondence_font_family' => $stored['correspondence.composition.font_family'],
                'correspondence_font_color' => $stored['correspondence.composition.font_color'],
                'correspondence_font_size' => $stored['correspondence.composition.font_size'],
                'correspondence_table_border_color' => $stored['correspondence.composition.table_border_color'],
                'correspondence_shape_border_color' => $stored['correspondence.composition.shape_border_color'],
                'correspondence_page_size' => $stored['correspondence.composition.page_size'],
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
        unset($data['stamp'], $data['remove_stamp'], $data['letterhead'], $data['remove_letterhead']);
        $previous = $this->settings->get(['organization.stamp.path' => null, 'organization.stamp.active' => '0', 'organization.letterhead.path' => null]);
        $stampPath = $previous['organization.stamp.path'];
        if ($request->boolean('remove_stamp')) {
            $stampPath = null;
            $data['stamp_active'] = false;
        } elseif ($request->hasFile('stamp')) {
            $stampPath = $request->file('stamp')->store('organization/stamp', 'local');
        }
        $stampActive = (bool) ($data['stamp_active'] ?? ($previous['organization.stamp.active'] === '1')) && filled($stampPath);

        $letterheadPath = $previous['organization.letterhead.path'];
        if ($request->boolean('remove_letterhead')) {
            $letterheadPath = null;
        } elseif ($request->hasFile('letterhead')) {
            $letterheadPath = $request->file('letterhead')->store('organization/letterhead', 'local');
        }

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
            'organization.postal_code' => $data['postal_code'] ?? null,
            'organization.stamp.path' => $stampPath,
            'organization.stamp.active' => $stampActive ? '1' : '0',
            'organization.letterhead.path' => $letterheadPath,
            'correspondence.numbering.format' => 'template',
            'correspondence.numbering.template' => $data['numbering_template'],
            'correspondence.registration_numbering.format' => 'template',
            'correspondence.registration_numbering.template' => $data['registration_numbering_template'],
            'correspondence.composition.method' => $data['composition_method'],
            'correspondence.composition.font_family' => $data['correspondence_font_family'],
            'correspondence.composition.font_color' => $data['correspondence_font_color'],
            'correspondence.composition.font_size' => (string) $data['correspondence_font_size'],
            'correspondence.composition.table_border_color' => $data['correspondence_table_border_color'],
            'correspondence.composition.shape_border_color' => $data['correspondence_shape_border_color'],
            'correspondence.composition.page_size' => $data['correspondence_page_size'],
            'correspondence.approval.incoming' => $data['approval_incoming'],
            'correspondence.approval.outgoing' => $data['approval_outgoing'],
            'correspondence.approval.internal' => $data['approval_internal'],
            OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::MANAGEMENT) => $data['management_department_id'] ?? null,
            OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::PROJECT_MANAGEMENT) => $data['project_management_department_id'] ?? null,
            OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT) => $data['secretariat_department_id'] ?? null,
            'projects.response_department.default' => $data['default_response_department_id'] ?? null,
        ]);

        if ($previous['organization.letterhead.path'] && $previous['organization.letterhead.path'] !== $letterheadPath) {
            Storage::disk('local')->delete($previous['organization.letterhead.path']);
        }

        if ($previous['organization.stamp.path'] && $previous['organization.stamp.path'] !== $stampPath) {
            Storage::disk('local')->delete($previous['organization.stamp.path']);
        }

        return back()->with('success', 'تنظیمات سازمان با موفقیت ذخیره شد.');
    }


    public function letterhead(Request $request): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);
        $path = $this->settings->get(['organization.letterhead.path' => null])['organization.letterhead.path'];
        abort_unless(filled($path) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
