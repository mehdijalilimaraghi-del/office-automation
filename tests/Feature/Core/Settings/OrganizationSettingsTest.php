<?php

namespace Tests\Feature\Core\Settings;

use App\Core\Permissions\Models\Permission;
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
