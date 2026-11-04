<?php

namespace Tests\Feature\Core\Settings;

use App\Core\Permissions\Models\Permission;
use App\Core\Departments\Models\Department;
use App\Core\Settings\Services\OrganizationDepartmentRoleService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Core\Settings\Models\SystemSetting;
use Tests\Feature\Core\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class OrganizationSettingsTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithPermissions;

    public function test_user_with_settings_permission_can_update_organization_settings(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['settings.manage']);
        $management = Department::query()->create([
            'name' => 'ساختار اداری',
            'code' => 'SET-MGMT',
            'is_active' => true,
        ]);
        $projects = Department::query()->create([
            'name' => 'دفتر طرح‌ها',
            'code' => 'SET-PROJECTS',
            'parent_id' => $management->id,
            'is_active' => true,
        ]);
        $secretariat = Department::query()->create([
            'name' => 'دبیرخانه مرکزی',
            'code' => 'SET-SECRETARIAT',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('settings.organization.update'), [
            'name' => 'شرکت نمونه',
            'short_name' => 'نمونه',
            'national_id' => '1234567890',
            'registration_number' => '1001',
            'economic_code' => '411111111111',
            'phone' => '02112345678',
            'email' => 'info@example.com',
            'website' => 'https://example.com',
            'address' => 'تهران',
            'stamp' => UploadedFile::fake()->image('stamp.png', 300, 300),
            'stamp_active' => true,
            'numbering_format' => 'custom',
            'numbering_order_first' => 'sequence',
            'numbering_order_second' => 'department',
            'numbering_order_third' => 'year',
            'numbering_separator' => '-',
            'numbering_padding' => 7,
            'registration_numbering_format' => 'custom',
            'registration_numbering_order_first' => 'sequence',
            'registration_numbering_order_second' => 'year',
            'registration_numbering_custom_fields_count' => 2,
            'registration_numbering_separator' => '/',
            'registration_numbering_padding' => 5,
            'composition_method' => 'both',
            'correspondence_font_family' => 'Vazirmatn',
            'approval_incoming' => 'approval',
            'approval_outgoing' => 'approval',
            'approval_internal' => 'direct',
            'management_department_id' => $management->id,
            'project_management_department_id' => $projects->id,
            'secretariat_department_id' => $secretariat->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('system_settings', [
            'key' => 'organization.name',
            'value' => 'شرکت نمونه',
        ]);

        $this->assertDatabaseHas('system_settings', [
            'key' => 'organization.national_id',
            'value' => '1234567890',
        ]);
        $this->assertDatabaseHas('system_settings', ['key' => 'organization.stamp.active', 'value' => '1']);
        $stampPath = SystemSetting::query()->where('key', 'organization.stamp.path')->value('value');
        $this->assertNotEmpty($stampPath);
        Storage::disk('local')->assertExists($stampPath);
        $this->assertDatabaseHas('system_settings', ['key' => 'correspondence.numbering.format', 'value' => 'custom']);
        $this->assertDatabaseHas('system_settings', ['key' => 'correspondence.numbering.custom_order', 'value' => 'sequence,department,year']);
        $this->assertDatabaseHas('system_settings', ['key' => 'correspondence.registration_numbering.format', 'value' => 'custom']);
        $this->assertDatabaseHas('system_settings', ['key' => 'correspondence.registration_numbering.custom_order', 'value' => 'sequence,year']);
        $this->assertDatabaseHas('system_settings', ['key' => 'correspondence.registration_numbering.padding', 'value' => '5']);
        $this->assertDatabaseHas('system_settings', ['key' => 'correspondence.composition.method', 'value' => 'both']);
        $this->assertDatabaseHas('system_settings', ['key' => 'correspondence.approval.outgoing', 'value' => 'approval']);

        $this->assertDatabaseHas('system_settings', [
            'key' => OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::MANAGEMENT),
            'value' => (string) $management->id,
        ]);
        $this->assertDatabaseHas('system_settings', [
            'key' => OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::PROJECT_MANAGEMENT),
            'value' => (string) $projects->id,
        ]);
        $this->assertDatabaseHas('system_settings', [
            'key' => OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT),
            'value' => (string) $secretariat->id,
        ]);
    }

    public function test_user_without_settings_permission_cannot_access_organization_settings(): void
    {
        $user = User::factory()->create();
        $this->grantPermissions($user, []);

        $this->actingAs($user)
            ->get(route('settings.organization.edit'))
            ->assertForbidden();
    }
    public function test_user_with_settings_permission_can_save_visual_correspondence_template(): void
    {
        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['settings.manage']);

        $elements = [[
            'id' => 'header_contact',
            'kind' => 'text',
            'label' => 'اطلاعات تماس',
            'content' => 'آدرس: {{address}} | تلفن تماس: {{tel}} | ایمیل: {{email}}',
            'x' => 8,
            'y' => 12,
            'width' => 84,
            'height' => 8,
            'font_size' => 10,
            'bold' => false,
            'align' => 'right',
            'visible' => true,
        ]];

        $this->actingAs($admin)
            ->put(route('correspondence.template.update'), ['elements' => $elements])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('system_settings', [
            'key' => 'correspondence.print.template',
            'value' => json_encode($elements, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
    }

    public function test_user_without_settings_permission_cannot_manage_correspondence_template(): void
    {
        $user = User::factory()->create();
        $this->grantPermissions($user, []);

        $this->actingAs($user)
            ->get(route('correspondence.template.edit'))
            ->assertForbidden();
    }

}
