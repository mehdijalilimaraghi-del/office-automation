<?php

namespace Tests\Feature\Core\ExternalParties;

use App\Core\ExternalParties\Models\ExternalContact;
use App\Core\ExternalParties\Models\ExternalDepartment;
use App\Core\ExternalParties\Models\ExternalOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Core\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class ExternalPartyControllerTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_authorized_user_can_manage_external_organization_department_and_contact(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $this->grantPermissions($user, [
            'external_parties.view',
            'external_parties.create',
            'external_parties.edit',
            'external_parties.delete',
        ]);

        $this->actingAs($user)
            ->get('/external-parties')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Core/ExternalParties/Index'));

        $this->actingAs($user)->post('/external-parties/organizations', [
            'name' => 'شرکت خارجی تست',
            'code' => 'EXT-001',
            'status' => 'active',
        ])->assertRedirect();

        $organization = ExternalOrganization::firstOrFail();

        $this->actingAs($user)->post('/external-parties/departments', [
            'external_organization_id' => $organization->id,
            'name' => 'واحد خرید',
            'status' => 'active',
        ])->assertRedirect();

        $department = ExternalDepartment::firstOrFail();

        $this->actingAs($user)->post('/external-parties/contacts', [
            'external_organization_id' => $organization->id,
            'external_department_id' => $department->id,
            'name' => 'علی رضایی',
            'position' => 'کارشناس خرید',
            'mobile' => '09120000000',
            'extension' => '123',
            'status' => 'active',
        ])->assertRedirect();

        $contact = ExternalContact::firstOrFail();

        $this->assertSame('علی رضایی', $contact->name);
        $this->assertSame('123', $contact->extension);

        $this->actingAs($user)->put("/external-parties/contacts/{$contact->id}", [
            'external_organization_id' => $organization->id,
            'external_department_id' => $department->id,
            'name' => 'علی رضایی ویرایش‌شده',
            'position' => 'مدیر خرید',
            'mobile' => '09120000000',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('external_contacts', ['name' => 'علی رضایی ویرایش‌شده']);

        $this->actingAs($user)->delete("/external-parties/contacts/{$contact->id}")->assertRedirect();
        $this->assertDatabaseMissing('external_contacts', ['id' => $contact->id]);
    }

    public function test_contact_endpoint_does_not_accept_unit_level_addressing_fields(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $this->grantPermissions($user, ['external_parties.view', 'external_parties.create']);

        $organization = ExternalOrganization::query()->create(['name' => 'سازمان اشخاص', 'status' => 'active']);
        $department = ExternalDepartment::query()->create([
            'external_organization_id' => $organization->id,
            'name' => 'واحد اشخاص',
            'status' => 'active',
        ]);

        $this->actingAs($user)->post('/external-parties/contacts', [
            'external_organization_id' => $organization->id,
            'external_department_id' => $department->id,
            'name' => 'فرد بدون خطاب مستقل',
            'organizational_title_prefix' => 'مدیریت',
            'position' => 'مدیر واحد مالی',
            'personal_salutation' => 'جناب آقای',
            'professional_title' => 'مهندس',
            'is_default' => true,
            'status' => 'active',
        ])->assertRedirect();

        $contact = ExternalContact::query()->where('name', 'فرد بدون خطاب مستقل')->firstOrFail();

        $this->assertNull($contact->organizational_title_prefix);
        $this->assertNull($contact->position);
        $this->assertNull($contact->personal_salutation);
        $this->assertNull($contact->professional_title);
        $this->assertFalse($contact->is_default);
        $this->assertNull($department->fresh()->manager_contact_id);
        $this->assertNull($department->fresh()->organizational_title_prefix);
    }


    public function test_external_department_stores_one_manager_and_shared_addressing_data(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $this->grantPermissions($user, ['external_parties.view', 'external_parties.create', 'external_parties.edit']);

        $organization = ExternalOrganization::query()->create(['name' => 'سازمان خطاب', 'status' => 'active']);
        $department = ExternalDepartment::query()->create([
            'external_organization_id' => $organization->id,
            'name' => 'واحد مالی',
            'status' => 'active',
        ]);
        $manager = ExternalContact::query()->create([
            'external_organization_id' => $organization->id,
            'external_department_id' => $department->id,
            'name' => 'علی رضایی',
            'status' => 'active',
        ]);

        $this->actingAs($user)->put("/external-parties/departments/{$department->id}", [
            'external_organization_id' => $organization->id,
            'name' => 'واحد مالی',
            'manager_contact_id' => $manager->id,
            'organizational_title_prefix' => 'مدیریت',
            'position' => 'مدیر واحد مالی',
            'personal_salutation' => 'جناب آقای',
            'professional_title' => 'دکتر',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('external_departments', [
            'id' => $department->id,
            'manager_contact_id' => $manager->id,
            'organizational_title_prefix' => 'مدیریت',
            'position' => 'مدیر واحد مالی',
            'personal_salutation' => 'جناب آقای',
            'professional_title' => 'دکتر',
        ]);
    }


    public function test_assigned_external_unit_manager_cannot_be_deactivated_or_moved(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $this->grantPermissions($user, ['external_parties.view', 'external_parties.edit']);

        $organization = ExternalOrganization::query()->create(['name' => 'سازمان مدیر', 'status' => 'active']);
        $department = ExternalDepartment::query()->create([
            'external_organization_id' => $organization->id,
            'name' => 'واحد مدیر',
            'status' => 'active',
        ]);
        $otherDepartment = ExternalDepartment::query()->create([
            'external_organization_id' => $organization->id,
            'name' => 'واحد دیگر',
            'status' => 'active',
        ]);
        $manager = ExternalContact::query()->create([
            'external_organization_id' => $organization->id,
            'external_department_id' => $department->id,
            'name' => 'مدیر واحد',
            'status' => 'active',
        ]);
        $department->update(['manager_contact_id' => $manager->id]);

        $this->actingAs($user)->put("/external-parties/contacts/{$manager->id}", [
            'external_organization_id' => $organization->id,
            'external_department_id' => $otherDepartment->id,
            'name' => $manager->name,
            'status' => 'active',
        ])->assertSessionHasErrors('contact');

        $this->actingAs($user)->put("/external-parties/contacts/{$manager->id}", [
            'external_organization_id' => $organization->id,
            'external_department_id' => $department->id,
            'name' => $manager->name,
            'status' => 'inactive',
        ])->assertSessionHasErrors('contact');

        $this->assertDatabaseHas('external_contacts', [
            'id' => $manager->id,
            'external_department_id' => $department->id,
            'status' => 'active',
        ]);
    }

    public function test_external_party_routes_require_permissions(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->get('/external-parties')
            ->assertForbidden();

        $this->actingAs($user)
            ->post('/external-parties/organizations', [
                'name' => 'Unauthorized',
                'status' => 'active',
            ])
            ->assertForbidden();
    }
}