<?php

namespace Tests\Feature\Core\CustomFields;

use App\Core\CustomFields\Models\CustomField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomFieldManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_manage_custom_field_definition(): void
    {
        $user = User::factory()->create();
        $permission = Permission::create(['name' => 'custom_fields.manage', 'guard_name' => 'web']);
        $user->givePermissionTo($permission);

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
