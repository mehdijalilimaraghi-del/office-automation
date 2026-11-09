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
            'letterhead' => UploadedFile::fake()->image('letterhead.png', 2481, 3508),
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
        $letterheadPath = SystemSetting::query()->where('key', 'organization.letterhead.path')->value('value');
        $this->assertNotEmpty($letterheadPath);
        Storage::disk('local')->assertExists($letterheadPath);
        $this->actingAs($admin)->get(route('settings.organization.letterhead'))->assertOk();
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
            'role' => 'repeat',
            'spacing' => 0,
            'font_family' => 'Arial',
            'page_format' => 'number_of',
            'label' => 'اطلاعات تماس',
            'content' => 'آدرس: {{address}} | تلفن تماس: {{tel}} | ایمیل: {{email}}',
            'x' => 8,
            'y' => 12,
            'width' => 84,
            'font_size' => 10,
            'bold' => false,
            'align' => 'right',
            'visible' => true,
        ]];

        $elements[] = [
            'id' => 'stamp_box',
            'kind' => 'stamp',
            'role' => 'issuer',
            'spacing' => 0,
            'font_family' => 'default',
            'page_format' => 'number_of',
            'label' => 'مهر سازمان',
            'content' => '',
            'x' => 75,
            'y' => 82,
            'width' => 15,
            'font_size' => 12,
            'bold' => false,
            'align' => 'center',
            'visible' => true,
        ];

        $elements[] = [
            'id' => 'letter_body', 'kind' => 'text', 'role' => 'body', 'spacing' => 36,
            'label' => 'متن اصلی نامه', 'content' => '', 'x' => 8, 'y' => 0, 'width' => 84,
            'font_size' => 13, 'font_family' => 'B Nazanin', 'page_format' => 'number_of',
            'bold' => false, 'align' => 'right', 'visible' => true,
        ];
        $elements[] = [
            'id' => 'page_number', 'kind' => 'text', 'role' => 'page_number', 'spacing' => 0,
            'label' => 'شماره صفحه', 'content' => '', 'x' => 8, 'y' => 94, 'width' => 30,
            'font_size' => 9, 'font_family' => 'default', 'page_format' => 'slash',
            'bold' => false, 'align' => 'center', 'visible' => true,
        ];

        $this->actingAs($admin)
            ->put(route('correspondence.template.update'), [
                'elements' => $elements,
                'page_layout' => ['top' => 16, 'right' => 20, 'bottom' => 24, 'left' => 19],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $storedTemplate = SystemSetting::query()->where('key', 'correspondence.print.template')->value('value');
        $actual = json_decode($storedTemplate, true, 512, JSON_THROW_ON_ERROR);
        $normalize = static function (array $items): array {
            foreach ($items as &$item) {
                ksort($item);
            }
            unset($item);
            return $items;
        };
        $this->assertSame($normalize($elements), $normalize($actual));
        $storedLayout = SystemSetting::query()->where('key', 'correspondence.print.page_layout')->value('value');
        $this->assertSame(
            ['top' => 16, 'right' => 20, 'bottom' => 24, 'left' => 19],
            json_decode($storedLayout, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function test_template_coordinates_are_rounded_before_page_bounds_validation(): void
    {
        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['settings.manage']);

        $elements = [[
            'id' => 'edge_item',
            'kind' => 'text',
            'role' => 'repeat',
            'spacing' => 0,
            'font_family' => 'default',
            'page_format' => 'number_of',
            'label' => 'المان لبه صفحه',
            'content' => 'نمونه',
            'x' => 70.000000001,
            'y' => 99.999999999,
            'width' => 30,
            'font_size' => 10,
            'bold' => false,
            'align' => 'right',
            'visible' => true,
        ]];

        $this->actingAs($admin)
            ->put(route('correspondence.template.update'), ['elements' => $elements])
            ->assertRedirect()
            ->assertSessionHas('success');

        $stored = SystemSetting::query()
            ->where('key', 'correspondence.print.template')
            ->value('value');
        $saved = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals(70, $saved[0]['x']);
        $this->assertEquals(100, $saved[0]['y']);
        $this->assertEquals(30, $saved[0]['width']);
    }


    public function test_template_designer_preview_uses_organization_settings_and_sample_letter_values(): void
    {
        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['settings.manage']);

        SystemSetting::query()->create(['key' => 'organization.name', 'value' => 'سازمان آزمایشی']);
        SystemSetting::query()->create(['key' => 'organization.address', 'value' => 'تهران، خیابان نمونه']);
        SystemSetting::query()->create(['key' => 'organization.phone', 'value' => '۰۲۱۱۲۳۴۵۶۷۸']);

        $expectedBody = implode("\n", [
            'با سلام و احترام؛',
            'پیرو موضوع مطرح‌شده، به استحضار می‌رساند متن این نامه به‌صورت نمونه برای بررسی چیدمان، قلم و فاصله‌گذاری درج شده است.',
            '',
            'خواهشمند است دستور فرمایید اقدامات لازم در این خصوص انجام شود.',
            '',
            'با سپاس',
        ]);

        $this->actingAs($admin)
            ->get(route('correspondence.template.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Correspondence/Template')
                ->where('previewValues.organization_name', 'سازمان آزمایشی')
                ->where('previewValues.address', 'تهران، خیابان نمونه')
                ->where('previewValues.tel', '۰۲۱۱۲۳۴۵۶۷۸')
                ->where('previewValues.subject', 'موضوع نمونه نامه')
                ->where('previewValues.creator_name', 'نام تنظیم‌کننده')
                ->where('pageLayout.top', 14)
                ->where('pageLayout.right', 18)
                ->where('pageLayout.bottom', 22)
                ->where('pageLayout.left', 18)
                ->where('previewBody', $expectedBody)
            );
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
