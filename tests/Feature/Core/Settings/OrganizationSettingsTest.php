<?php

namespace Tests\Feature\Core\Settings;

use App\Core\Permissions\Models\Permission;
use App\Core\Departments\Models\Department;
use App\Core\Settings\Services\OrganizationDepartmentRoleService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Core\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class OrganizationSettingsTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithPermissions;

    public function test_user_with_settings_permission_can_update_organization_settings(): void
    {
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
}
