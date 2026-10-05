<?php

namespace Tests\Feature\Core;

use App\Core\Departments\Models\Department;
use App\Core\Departments\Models\Position;
use App\Core\Roles\Models\Role;
use App\Core\ExternalParties\Models\ExternalContact;
use App\Core\ExternalParties\Models\ExternalDepartment;
use App\Core\ExternalParties\Models\ExternalOrganization;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemCodeGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_entities_receive_generated_system_codes(): void
    {
        $department = Department::create([
            'name' => 'واحد آزمایشی',
            'icon' => 'building-2',
            'is_active' => true,
        ]);

        $position = Position::create([
            'department_id' => $department->id,
            'name' => 'سمت آزمایشی',
            'is_active' => true,
        ]);

        $role = Role::create([
            'name' => 'نقش آزمایشی',
            'is_active' => true,
            'is_system' => false,
        ]);

        $user = User::factory()->create();

        $project = Project::create([
            'name' => 'پروژه آزمایشی',
            'description' => null,
            'status' => 'planning',
            'manager_id' => $user->id,
            'created_by' => $user->id,
            'is_active' => true,
            'is_system_default' => false,
        ]);

        $projectRole = ProjectRole::create([
            'project_id' => $project->id,
            'name' => 'نقش پروژه آزمایشی',
            'is_active' => true,
        ]);

        $externalOrganization = ExternalOrganization::create([
            'name' => 'سازمان برون‌سازمانی آزمایشی',
            'status' => 'active',
        ]);

        $externalDepartment = ExternalDepartment::create([
            'external_organization_id' => $externalOrganization->id,
            'name' => 'واحد برون‌سازمانی آزمایشی',
            'status' => 'active',
        ]);

        $externalContact = ExternalContact::create([
            'external_organization_id' => $externalOrganization->id,
            'external_department_id' => $externalDepartment->id,
            'name' => 'مخاطب آزمایشی',
            'status' => 'active',
        ]);


        $this->assertSame(
            sprintf('ORG-%06d', $department->id),
            $department->refresh()->code,
        );
        $this->assertSame(
            sprintf('POS-%06d', $position->refresh()->id),
            $position->refresh()->code,
        );
        $this->assertSame(
            sprintf('ROLE-%06d', $role->refresh()->id),
            $role->refresh()->code,
        );
        $this->assertSame(
            sprintf('PRJ-%06d', $project->refresh()->id),
            $project->refresh()->code,
        );
        $this->assertSame(
            sprintf('PRL-%06d', $projectRole->refresh()->id),
            $projectRole->refresh()->code,
        );

        $this->assertSame(
            sprintf('EXTORG-%06d', $externalOrganization->refresh()->id),
            $externalOrganization->refresh()->code,
        );
        $this->assertSame(
            sprintf('EXTDPT-%06d', $externalDepartment->refresh()->id),
            $externalDepartment->refresh()->code,
        );
        $this->assertSame(
            sprintf('EXTCNT-%06d', $externalContact->refresh()->id),
            $externalContact->refresh()->code,
        );
    }

    public function test_system_role_keeps_its_explicit_system_code(): void
    {
        $role = Role::create([
            'name' => 'نقش سیستمی آزمایشی',
            'code' => 'SYSTEM_TEST',
            'is_active' => true,
            'is_system' => true,
        ]);

        $this->assertSame('SYSTEM_TEST', $role->refresh()->code);
    }
}
