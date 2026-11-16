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
            'postal_code' => '1234567890',
            'stamp' => UploadedFile::fake()->image('stamp.png', 300, 300),
            'letterhead' => UploadedFile::fake()->image('letterhead.png', 2481, 3508),
            'stamp_active' => true,
            'numbering_template' => '{{PYY}}/{{Dept}}/{{Num-7-r}}',
            'registration_numbering_template' => '{{GYY}}-{{Num-5-c}}',
            'composition_method' => 'both',
            'correspondence_font_family' => 'Vazirmatn',
            'correspondence_font_color' => '#202124',
            'correspondence_font_size' => 13,
            'correspondence_table_border_color' => '#112233',
            'correspondence_shape_border_color' => '#445566',
            'correspondence_page_size' => 'A5',
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
        $this->assertDatabaseHas('system_settings', ['key' => 'organization.postal_code', 'value' => '1234567890']);
        $this->assertDatabaseHas('system_settings', ['key' => 'organization.stamp.active', 'value' => '1']);
        $letterheadPath = SystemSetting::query()->where('key', 'organization.letterhead.path')->value('value');
        $this->assertNotEmpty($letterheadPath);
        Storage::disk('local')->assertExists($letterheadPath);
        $this->actingAs($admin)->get(route('settings.organization.letterhead'))->assertOk();
        $stampPath = SystemSetting::query()->where('key', 'organization.stamp.path')->value('value');
        $this->assertNotEmpty($stampPath);
        Storage::disk('local')->assertExists($stampPath);
        $this->assertDatabaseHas('system_settings', ['key' => 'correspondence.numbering.format', 'value' => 'template']);
        $this->assertDatabaseHas('system_settings', ['key' => 'correspondence.numbering.template', 'value' => '{{PYY}}/{{Dept}}/{{Num-7-r}}']);
        $this->assertDatabaseHas('system_settings', ['key' => 'correspondence.registration_numbering.format', 'value' => 'template']);
        $this->assertDatabaseHas('system_settings', ['key' => 'correspondence.registration_numbering.template', 'value' => '{{GYY}}-{{Num-5-c}}']);
        $this->assertDatabaseHas('system_settings', ['key' => 'correspondence.composition.method', 'value' => 'both']);
        $this->assertDatabaseHas('system_settings', ['key' => 'correspondence.composition.font_color', 'value' => '#202124']);
        $this->assertDatabaseHas('system_settings', ['key' => 'correspondence.composition.font_size', 'value' => '13']);
        $this->assertDatabaseHas('system_settings', ['key' => 'correspondence.composition.table_border_color', 'value' => '#112233']);
        $this->assertDatabaseHas('system_settings', ['key' => 'correspondence.composition.shape_border_color', 'value' => '#445566']);
        $this->assertDatabaseHas('system_settings', ['key' => 'correspondence.composition.page_size', 'value' => 'A5']);
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

    public function test_template_designer_uses_a5_when_selected_in_organization_settings(): void
    {
        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['settings.manage']);

        SystemSetting::query()->create([
            'key' => 'correspondence.composition.page_size',
            'value' => 'A5',
        ]);

        $this->actingAs($admin)
            ->get(route('correspondence.template.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Correspondence/Template')
                ->where('pageSize', 'A5')
            );
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
            'role' => 'header',
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
            'id' => 'footer_contact',
            'kind' => 'text',
            'role' => 'footer',
            'spacing' => 0,
            'font_family' => 'Arial',
            'page_format' => 'number_of',
            'label' => 'اطلاعات پاصفحه',
            'content' => '{{website}}',
            'x' => 8,
            'y' => 94,
            'width' => 84,
            'font_size' => 9,
            'bold' => false,
            'align' => 'center',
            'visible' => true,
        ];

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
                'page_layout' => ['top' => 16, 'right' => 20, 'bottom' => 24, 'left' => 19, 'header_height' => 72, 'footer_height' => 28],
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
            ['top' => 16, 'right' => 20, 'bottom' => 24, 'left' => 19, 'header_height' => 72, 'footer_height' => 28],
            json_decode($storedLayout, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    private function paginationElements(): array
    {
        $letter = [
            'id' => 'letter_pages', 'kind' => 'text', 'role' => 'page_number', 'spacing' => 0,
            'label' => 'شماره صفحه نامه', 'content' => '', 'x' => 8, 'y' => 94, 'width' => 30,
            'font_size' => 9, 'font_family' => 'default', 'page_format' => 'slash',
            'bold' => false, 'align' => 'right', 'visible' => true,
        ];

        return [$letter, [
            ...$letter, 'id' => 'appendix_pages', 'role' => 'appendix_page_number',
            'label' => 'شماره صفحه پیوست', 'page_prefix' => 'ضمیمه', 'x' => 60, 'y' => 84, 'font_size' => 11,
            'font_family' => 'Tahoma', 'page_format' => 'page', 'align' => 'left',
        ]];
    }

    private function paginationLetter(\App\Models\User $user): \App\Modules\Correspondence\Models\Correspondence
    {
        return \App\Modules\Correspondence\Models\Correspondence::query()->create([
            'type' => 'internal', 'subject' => 'نامه با پیوست', 'body' => '<p>نامه</p>',
            'appendix_mode' => 'editor', 'appendix_body' => '<p>پیوست</p>',
            'created_by' => $user->id, 'status' => 'registered',
        ]);
    }

    public function test_independent_appendix_page_number_settings_survive_save_and_reach_print(): void
    {
        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['settings.manage', 'correspondence.view']);
        $elements = $this->paginationElements();
        $this->actingAs($admin)->put(route('correspondence.template.update'), ['elements' => $elements, 'appendix_heading' => 'مدارک همراه'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('system_settings', [
            'key' => 'correspondence.print.appendix_numbering_configured', 'value' => '1',
        ]);
        foreach ([route('correspondence.template.edit') => 'elements',
            route('correspondence.print', $this->paginationLetter($admin)) => 'templateElements'] as $url => $prop) {
            $this->get($url)->assertOk()->assertInertia(fn ($page) => $page
                ->where($prop.'.0.page_format', 'slash')
                ->where($prop.'.0.y', 94)
                ->where($prop.'.1.role', 'appendix_page_number')
                ->where($prop.'.1.page_format', 'page')
                ->where($prop.'.1.x', 60)
                ->where($prop.'.1.y', 84)
                ->where($prop.'.1.font_family', 'Tahoma')
                ->where($prop.'.1.page_prefix', 'ضمیمه')
                ->where('appendixHeading', 'مدارک همراه'));
        }
        $elements[1]['page_prefix'] = '';
        $this->put(route('correspondence.template.update'), ['elements' => $elements, 'appendix_heading' => ''])->assertRedirect();
        $this->get(route('correspondence.print', $this->paginationLetter($admin)))->assertOk()->assertInertia(fn ($page) => $page
            ->where('templateElements.1.page_prefix', null)->where('appendixHeading', ''));

        $elements[1]['page_format'] = 'invalid';
        $this->put(route('correspondence.template.update'), ['elements' => $elements, 'appendix_heading' => 'مدارک همراه'])
            ->assertSessionHasErrors('elements.1.page_format');
    }

    public function test_legacy_appendix_numbering_inherits_letter_position_without_writing_settings(): void
    {
        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['settings.manage', 'correspondence.view']);
        $letterNumber = $this->paginationElements()[0];
        app(\App\Core\Settings\Services\SystemSettingsService::class)->set([
            'correspondence.print.template' => json_encode([$letterNumber], JSON_THROW_ON_ERROR),
        ]);
        foreach ([route('correspondence.template.edit') => 'elements',
            route('correspondence.print', $this->paginationLetter($admin)) => 'templateElements'] as $url => $prop) {
            $this->actingAs($admin)->get($url)->assertOk()->assertInertia(fn ($page) => $page
                ->where($prop.'.2.role', 'appendix_page_number')
                ->where($prop.'.2.x', 8)
                ->where($prop.'.2.y', 94)
                ->where($prop.'.2.page_format', 'page'));
        }
        $this->assertDatabaseMissing('system_settings', ['key' => 'correspondence.print.appendix_numbering_configured']);
        $stored = SystemSetting::query()->where('key', 'correspondence.print.template')->value('value');
        $this->assertCount(1, json_decode($stored, true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_removed_appendix_page_counter_is_not_recreated_after_saving_template(): void
    {
        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['settings.manage', 'correspondence.view']);
        $this->actingAs($admin)->put(route('correspondence.template.update'), [
            'elements' => [$this->paginationElements()[0]],
            'appendix_numbering_configured' => true,
        ])->assertRedirect()->assertSessionHas('success');
        foreach ([route('correspondence.template.edit') => 'elements',
            route('correspondence.print', $this->paginationLetter($admin)) => 'templateElements'] as $url => $prop) {
            $this->get($url)->assertOk()->assertInertia(fn ($page) => $page
                ->where($prop, fn ($items) => !collect($items)->contains('role', 'appendix_page_number')));
        }
    }

    public function test_old_template_save_payload_preserves_legacy_appendix_numbering(): void
    {
        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['settings.manage']);
        $this->actingAs($admin)->put(route('correspondence.template.update'), [
            'elements' => [$this->paginationElements()[0]],
        ])->assertRedirect()->assertSessionHas('success');
        $this->get(route('correspondence.template.edit'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('elements.2.role', 'appendix_page_number')
            ->where('elements.2.page_format', 'page'));
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
                ->where('pageSize', 'A4')
                ->where('pageLayout.top', 14)
                ->where('pageLayout.right', 18)
                ->where('pageLayout.bottom', 22)
                ->where('pageLayout.left', 18)
                ->where('pageLayout.header_height', 70)
                ->where('pageLayout.footer_height', 25)
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
