<?php

declare(strict_types=1);

namespace Tests\Feature\Core\ExternalParties;

use App\Core\ExternalParties\Contracts\ExternalPartyServiceInterface;
use App\Core\ExternalParties\Models\ExternalDepartment;
use App\Core\ExternalParties\Models\ExternalOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExternalPartyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_department_cannot_be_moved_under_its_own_descendant(): void
    {
        $organization = ExternalOrganization::query()->create([
            'name' => 'سازمان آزمون',
            'status' => 'active',
        ]);
        $parent = ExternalDepartment::query()->create([
            'external_organization_id' => $organization->id,
            'name' => 'واحد والد',
            'status' => 'active',
        ]);
        $child = ExternalDepartment::query()->create([
            'external_organization_id' => $organization->id,
            'parent_id' => $parent->id,
            'name' => 'واحد فرزند',
            'status' => 'active',
        ]);

        try {
            app(ExternalPartyServiceInterface::class)->updateDepartment($parent, [
                'external_organization_id' => $organization->id,
                'parent_id' => $child->id,
                'name' => $parent->name,
                'status' => 'active',
            ]);
            $this->fail('Expected a validation exception for a circular external department hierarchy.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('parent_id', $exception->errors());
        }
    }

    public function test_task_context_rejects_inactive_external_organizations_and_departments(): void
    {
        $inactiveOrganization = ExternalOrganization::query()->create([
            'name' => 'سازمان غیرفعال',
            'status' => 'inactive',
        ]);

        try {
            app(ExternalPartyServiceInterface::class)->validateTaskContext([
                'external_organization_id' => $inactiveOrganization->id,
            ]);
            $this->fail('Expected inactive external organization to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('external_organization_id', $exception->errors());
        }

        $activeOrganization = ExternalOrganization::query()->create([
            'name' => 'سازمان فعال',
            'status' => 'active',
        ]);
        $inactiveDepartment = ExternalDepartment::query()->create([
            'external_organization_id' => $activeOrganization->id,
            'name' => 'واحد غیرفعال',
            'status' => 'inactive',
        ]);

        try {
            app(ExternalPartyServiceInterface::class)->validateTaskContext([
                'external_organization_id' => $activeOrganization->id,
                'external_department_id' => $inactiveDepartment->id,
            ]);
            $this->fail('Expected inactive external department to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('external_department_id', $exception->errors());
        }
    }
}
