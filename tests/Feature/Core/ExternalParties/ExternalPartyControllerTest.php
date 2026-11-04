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

    public function test_only_one_default_contact_is_kept_per_organization_or_department_scope(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $this->grantPermissions($user, ['external_parties.view', 'external_parties.create', 'external_parties.edit']);
        $organization = ExternalOrganization::query()->create(['name' => 'سازمان پیش‌فرض', 'status' => 'active']);
        $department = ExternalDepartment::query()->create([
            'external_organization_id' => $organization->id,
            'name' => 'واحد پیش‌فرض',
            'status' => 'active',
        ]);

        $first = ExternalContact::query()->create([
            'external_organization_id' => $organization->id,
            'external_department_id' => $department->id,
            'name' => 'مخاطب اول',
            'is_default' => true,
            'status' => 'active',
        ]);

        $this->actingAs($user)->post('/external-parties/contacts', [
            'external_organization_id' => $organization->id,
            'external_department_id' => $department->id,
            'name' => 'مخاطب دوم',
            'organizational_title_prefix' => 'مدیریت',
            'position' => 'مدیر واحد مالی',
            'personal_salutation' => 'جناب آقای',
            'professional_title' => 'مهندس',
            'is_default' => true,
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('external_contacts', ['id' => $first->id, 'is_default' => false]);
        $second = ExternalContact::query()->where('name', 'مخاطب دوم')->firstOrFail();
        $this->assertTrue($second->is_default);
        $this->assertSame('مدیریت', $second->organizational_title_prefix);
        $this->assertSame('جناب آقای', $second->personal_salutation);

        $organizationDefault = ExternalContact::query()->create([
            'external_organization_id' => $organization->id,
            'name' => 'مخاطب پیش‌فرض سازمان',
            'is_default' => true,
            'status' => 'active',
        ]);

        $this->assertTrue($organizationDefault->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
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