<?php

namespace Tests\Feature\Core;

use App\Core\CustomFields\Actions\CreateCustomFieldAction;
use App\Core\CustomFields\Actions\UpdateCustomFieldAction;
use App\Core\CustomFields\Models\CustomField;
use App\Core\CustomFields\Services\CustomFieldService;
use App\Core\Departments\Models\Department;
use App\Core\Departments\Models\Position;
use App\Core\Roles\Models\Role;
use App\Core\ExternalParties\Models\ExternalContact;
use App\Core\ExternalParties\Models\ExternalDepartment;
use App\Core\ExternalParties\Models\ExternalOrganization;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomFieldManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_field_key_is_generated_by_the_system_and_remains_stable(): void
    {
        $field = app(CreateCustomFieldAction::class)->execute([
            'entity_type' => User::class,
            'label' => 'کد پرسنلی قدیمی',
            'type' => 'text',
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 0,
            'options' => null,
        ]);

        $key = $field->key;

        $this->assertMatchesRegularExpression('/^cf_[a-z0-9]{16}$/', $key);
        $this->assertNotEmpty($key);

        $field = app(UpdateCustomFieldAction::class)->execute($field, [
            'entity_type' => User::class,
            'label' => 'شماره پرسنلی قدیمی',
            'type' => 'text',
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->assertSame($key, $field->key);
        $this->assertSame('شماره پرسنلی قدیمی', $field->label);
    }

    public function test_custom_field_service_supports_the_v1_operational_entities(): void
    {
        $supported = CustomFieldService::supportedEntities();

        foreach ([
            User::class,
            Department::class,
            Position::class,
            Role::class,
            Project::class,
            Task::class,
            ExternalOrganization::class,
            ExternalDepartment::class,
            ExternalContact::class,
        ] as $entityType) {
            $this->assertArrayHasKey($entityType, $supported);
        }
    }

    public function test_custom_field_definition_has_a_unique_key_per_entity(): void
    {
        $first = CustomField::create([
            'entity_type' => User::class,
            'key' => 'cf_test_first',
            'label' => 'فیلد اول',
            'type' => 'text',
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 0,
            'options' => null,
        ]);

        $second = app(CreateCustomFieldAction::class)->execute([
            'entity_type' => User::class,
            'label' => 'فیلد دوم',
            'type' => 'text',
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 1,
            'options' => null,
        ]);

        $this->assertNotSame($first->key, $second->key);
        $this->assertSame(2, CustomField::query()
            ->where('entity_type', User::class)
            ->count());
    }
}
