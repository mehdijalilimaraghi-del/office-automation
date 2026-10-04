<?php

namespace Tests\Feature\Core\CustomFields;

use App\Core\CustomFields\Models\CustomField;
use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomFieldManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_manage_custom_field_definition(): void
    {
        $user = User::factory()->create();
        $permission = Permission::create(['name' => 'مدیریت فیلدهای سفارشی', 'code' => 'custom_fields.manage', 'is_active' => true, 'is_system' => true]);
        $role = Role::create(['name' => 'Custom Field Admin', 'code' => 'CUSTOM_FIELD_ADMIN', 'is_active' => true, 'is_system' => false]);
        $role->permissions()->attach($permission->id, ['scope' => 'organization']);
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->post(route('custom-fields.store'), [
            'entity_type' => User::class,
            'key' => 'personnel_grade',
            'label' => 'گرید پرسنلی',
            'type' => 'select',
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 1,
            'options' => ['A', 'B'],
        ]);

        $response->assertRedirect();
        $field = CustomField::firstOrFail();
        $this->assertSame(['A', 'B'], $field->options);

        $this->actingAs($user)->put(route('custom-fields.update', $field), [
            'entity_type' => User::class,
            'key' => 'personnel_grade',
            'label' => 'گرید',
            'type' => 'select',
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 2,
            'options' => ['C'],
        ])->assertRedirect();

        $this->assertDatabaseHas('custom_fields', ['id' => $field->id, 'label' => 'گرید']);
    }
}
