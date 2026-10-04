<?php

namespace Tests\Feature\Core\CustomFields;

use App\Core\CustomFields\Models\CustomField;
use App\Core\CustomFields\Services\CustomFieldService;
use App\Core\Users\Actions\DeleteUserAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomFieldServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_custom_fields_can_be_defined_and_synced(): void
    {
        $user = User::factory()->create();

        CustomField::create([
            'entity_type' => User::class,
            'key' => 'personnel_grade',
            'label' => 'گرید پرسنلی',
            'type' => 'select',
            'options' => ['A', 'B', 'C'],
            'is_required' => true,
        ]);

        CustomField::create([
            'entity_type' => User::class,
            'key' => 'years_of_service',
            'label' => 'سابقه',
            'type' => 'number',
        ]);

        $service = app(CustomFieldService::class);
        $service->sync($user, [
            'personnel_grade' => 'B',
            'years_of_service' => 7,
        ]);

        $this->assertSame([
            'personnel_grade' => 'B',
            'years_of_service' => 7,
        ], $service->values($user));
    }

    public function test_invalid_required_or_select_value_is_rejected(): void
    {
        $user = User::factory()->create();

        CustomField::create([
            'entity_type' => User::class,
            'key' => 'personnel_grade',
            'label' => 'گرید پرسنلی',
            'type' => 'select',
            'options' => ['A', 'B'],
            'is_required' => true,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(CustomFieldService::class)->sync($user, [
            'personnel_grade' => 'X',
        ]);
    }

    public function test_user_deletion_cleans_custom_field_values(): void
    {
        $user = User::factory()->create();

        $field = CustomField::create([
            'entity_type' => User::class,
            'key' => 'badge_number',
            'label' => 'شماره کارت',
            'type' => 'text',
        ]);

        app(CustomFieldService::class)->sync($user, ['badge_number' => '123']);

        app(DeleteUserAction::class)->execute($user);

        $this->assertDatabaseMissing('custom_field_values', [
            'custom_field_id' => $field->id,
            'entity_type' => User::class,
            'entity_id' => $user->id,
        ]);
    }

    public function test_empty_optional_value_is_removed(): void
    {
        $user = User::factory()->create();

        $field = CustomField::create([
            'entity_type' => User::class,
            'key' => 'badge_number',
            'label' => 'شماره کارت',
            'type' => 'text',
        ]);

        $service = app(CustomFieldService::class);
        $service->sync($user, ['badge_number' => '123']);
        $service->sync($user, ['badge_number' => null]);

        $this->assertDatabaseMissing('custom_field_values', [
            'custom_field_id' => $field->id,
            'entity_id' => $user->id,
        ]);
    }
}
