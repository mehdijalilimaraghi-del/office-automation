<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Correspondence;

use App\Core\Permissions\Models\Permission;
use App\Core\Permissions\Enums\PermissionScope;
use App\Core\Roles\Models\Role;
use App\Core\Departments\Models\Department;
use App\Core\Settings\Services\OrganizationDepartmentRoleService;
use App\Core\Settings\Services\SystemSettingsService;
use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class CorrespondenceHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_view_permission_can_open_correspondence_index(): void
    {
        $user = $this->userWithPermission('correspondence.view');
        Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'نامه آزمایشی',
            'status' => Correspondence::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('correspondence.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Correspondence/Index')
                ->where('correspondences.total', 1)
                ->where('correspondences.data.0.subject', 'نامه آزمایشی'));
    }

    public function test_user_with_view_permission_can_open_correspondence_details(): void
    {
        $user = $this->userWithPermission('correspondence.view');
        $correspondence = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'جزئیات نامه آزمایشی',
            'body' => '<p>متن نامه</p>',
            'status' => Correspondence::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('correspondence.show', $correspondence))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Correspondence/Show')
                ->where('correspondence.subject', 'جزئیات نامه آزمایشی')
                ->where('correspondence.body', '<p>متن نامه</p>')
                ->where('attachments', []));
    }

    public function test_registered_ordinary_letter_is_visible_to_any_viewer(): void
    {
        $creator = $this->userWithPermission('correspondence.create');
        $viewer = $this->userWithPermission('correspondence.view');
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_OUTGOING,
            'classification' => Correspondence::CLASSIFICATION_ORDINARY,
            'subject' => 'نامه عادی ثبت‌شده',
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => '1405/000001',
            'created_by' => $creator->id,
        ]);

        $this->actingAs($viewer)->get(route('correspondence.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('correspondences.total', 1));
        $this->actingAs($viewer)->get(route('correspondence.show', $letter))->assertOk();
    }

    public function test_unrelated_viewer_cannot_open_confidential_letter(): void
    {
        $creator = $this->userWithPermission('correspondence.create');
        $viewer = $this->userWithPermission('correspondence.view');
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'classification' => Correspondence::CLASSIFICATION_CONFIDENTIAL,
            'subject' => 'نامه محرمانه',
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => '1405/000002',
            'created_by' => $creator->id,
        ]);

        $this->actingAs($viewer)->get(route('correspondence.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('correspondences.total', 0));
        $this->actingAs($viewer)->get(route('correspondence.show', $letter))->assertForbidden();
    }

    public function test_related_viewer_can_open_confidential_letter(): void
    {
        $creator = $this->userWithPermission('correspondence.create');
        $viewer = $this->userWithPermission('correspondence.view');
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'classification' => Correspondence::CLASSIFICATION_CONFIDENTIAL,
            'subject' => 'نامه محرمانه مرتبط',
            'status' => Correspondence::STATUS_REGISTERED,
            'created_by' => $creator->id,
        ]);
        $letter->authorizedUsers()->attach($viewer->id, [
            'reason' => 'participant',
            'granted_by' => $creator->id,
        ]);

        $this->actingAs($viewer)->get(route('correspondence.show', $letter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('correspondence.subject', 'نامه محرمانه مرتبط'));
    }

    public function test_user_with_create_permission_can_create_editor_draft_and_unsafe_markup_is_removed(): void
    {
        $user = $this->userWithPermission('correspondence.create');
        $recipientDepartmentId = $this->recipientDepartmentId();

        $this->actingAs($user)->post(route('correspondence.store'), [
            'type' => 'internal',
            'recipient_department_id' => $recipientDepartmentId,
            'subject' => '  نامه داخلی  ',
            'composition_mode' => 'editor',
            'body' => '<p>متن <strong>مهم</strong><img src=x onerror=alert(1)></p>',
            'sender_name' => 'فرستنده',
        ])->assertRedirect(route('correspondence.index'));

        $this->assertDatabaseHas('correspondences', [
            'type' => 'internal',
            'subject' => 'نامه داخلی',
            'body' => '<p>متن <strong>مهم</strong></p>',
            'status' => 'draft',
            'registration_number' => null,
            'created_by' => $user->id,
            'recipient_department_id' => $recipientDepartmentId,
            'sender_name' => $user->name,
        ]);
    }

    public function test_file_mode_requires_at_least_one_attachment(): void
    {
        $user = $this->userWithPermission('correspondence.create');

        $this->actingAs($user)->from(route('correspondence.create'))->post(route('correspondence.store'), [
            'type' => 'outgoing',
            'subject' => 'نامه صادره',
            'composition_mode' => 'file',
        ])->assertRedirect(route('correspondence.create'))
            ->assertSessionHasErrors('attachments');

        $this->assertDatabaseCount('correspondences', 0);
    }

    public function test_user_can_create_file_only_draft_with_attachment(): void
    {
        $user = $this->userWithPermission('correspondence.create');
        $recipientDepartmentId = $this->recipientDepartmentId();
        $documents = Mockery::mock(DocumentService::class);
        $documents->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data, UploadedFile $file, User $creator, Correspondence $linkable, string $purpose): bool {
                return $data['document_type'] === 'correspondence_attachment'
                    && $file->getClientOriginalName() === 'letter.pdf'
                    && $creator->is(auth()->user())
                    && $linkable->exists
                    && $purpose === 'correspondence_attachment';
            })
            ->andReturn(new Document());
        $this->instance(DocumentService::class, $documents);

        $this->actingAs($user)->post(route('correspondence.store'), [
            'type' => 'internal',
            'recipient_department_id' => $recipientDepartmentId,
            'subject' => 'نامه پیوست‌شده',
            'composition_mode' => 'file',
            'attachments' => [UploadedFile::fake()->create('letter.pdf', 10, 'application/pdf')],
        ])->assertRedirect(route('correspondence.index'));

        $this->assertDatabaseHas('correspondences', [
            'subject' => 'نامه پیوست‌شده',
            'body' => null,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
    }

    public function test_active_staff_user_has_baseline_correspondence_view_and_draft_access(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->get(route('correspondence.index'))->assertOk();
        $this->actingAs($user)->get(route('correspondence.create'))->assertOk();
    }

    public function test_non_secretariat_can_create_internal_and_outgoing_drafts_but_not_incoming_letters(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $this->actingAs($user)->get(route('correspondence.create'))->assertOk()->assertInertia(fn ($page) => $page->where('types', [['value' => Correspondence::TYPE_OUTGOING, 'label' => 'صادره'], ['value' => Correspondence::TYPE_INTERNAL, 'label' => 'داخلی']]));
        $this->actingAs($user)->post(route('correspondence.store'), ['type' => Correspondence::TYPE_INCOMING, 'subject' => 'نامه وارده غیرمجاز', 'composition_mode' => 'editor', 'body' => '<p>متن</p>'])->assertForbidden();
        $this->assertDatabaseCount('correspondences', 0);
        $this->actingAs($user)->post(route('correspondence.store'), ['type' => Correspondence::TYPE_OUTGOING, 'subject' => 'پیش‌نویس صادره مجاز', 'composition_mode' => 'editor', 'body' => '<p>متن</p>'])->assertRedirect(route('correspondence.index'));
        $this->actingAs($user)->post(route('correspondence.store'), ['type' => Correspondence::TYPE_INTERNAL, 'recipient_department_id' => $this->recipientDepartmentId(), 'subject' => 'نامه داخلی مجاز', 'composition_mode' => 'editor', 'body' => '<p>متن</p>'])->assertRedirect(route('correspondence.index'));
        $this->assertDatabaseCount('correspondences', 2);
    }

    public function test_only_secretariat_can_register_outgoing_draft(): void
    {
        $creator = User::factory()->create(['status' => 'active']);
        $letter = Correspondence::query()->create(['type' => Correspondence::TYPE_OUTGOING, 'subject' => 'پیش‌نویس صادره', 'status' => Correspondence::STATUS_DRAFT, 'created_by' => $creator->id]);
        $this->actingAs($creator)->post(route('correspondence.register', $letter))->assertForbidden();
        $this->assertDatabaseHas('correspondences', ['id' => $letter->id, 'status' => Correspondence::STATUS_DRAFT, 'registration_number' => null]);
    }

    public function test_internal_letter_creator_can_register_their_own_internal_draft(): void
    {
        $creator = User::factory()->create(['status' => 'active']);
        $letter = Correspondence::query()->create(['type' => Correspondence::TYPE_INTERNAL, 'subject' => 'نامه داخلی', 'status' => Correspondence::STATUS_DRAFT, 'created_by' => $creator->id]);
        $this->actingAs($creator)->post(route('correspondence.register', $letter))->assertRedirect();
        $this->assertDatabaseHas('correspondences', ['id' => $letter->id, 'status' => Correspondence::STATUS_REGISTERED, 'registered_by' => $creator->id]);
    }

    public function test_configured_secretariat_can_create_incoming_letter_and_register_outgoing_draft(): void
    {
        $secretariatUser = User::factory()->create(['status' => 'active']);
        $department = Department::query()->create(['name' => 'دبیرخانه تست', 'code' => 'TEST-SEC', 'is_active' => true, 'manager_id' => $secretariatUser->id]);
        app(SystemSettingsService::class)->set([OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT) => (string) $department->id]);
        $this->actingAs($secretariatUser)->get(route('correspondence.create'))->assertOk()->assertInertia(fn ($page) => $page->where('types.0.value', Correspondence::TYPE_INCOMING));
        $this->actingAs($secretariatUser)->post(route('correspondence.store'), ['type' => Correspondence::TYPE_INCOMING, 'subject' => 'نامه وارده دبیرخانه', 'composition_mode' => 'editor', 'body' => '<p>متن</p>'])->assertRedirect(route('correspondence.index'));
        $this->assertDatabaseHas('correspondences', ['type' => Correspondence::TYPE_INCOMING, 'subject' => 'نامه وارده دبیرخانه', 'created_by' => $secretariatUser->id]);
        app(SystemSettingsService::class)->set(['correspondence.approval.outgoing' => 'direct']);
        $letter = Correspondence::query()->create(['type' => Correspondence::TYPE_OUTGOING, 'subject' => 'پیش‌نویس صادره برای دبیرخانه', 'status' => Correspondence::STATUS_DRAFT, 'created_by' => User::factory()->create(['status' => 'active'])->id]);
        $this->actingAs($secretariatUser)->post(route('correspondence.register', $letter))->assertRedirect();
        $this->assertDatabaseHas('correspondences', ['id' => $letter->id, 'status' => Correspondence::STATUS_REGISTERED, 'registered_by' => $secretariatUser->id]);
    }
    private function recipientDepartmentId(): int
    {
        return Department::query()->create([
            'name' => 'واحد گیرنده '.Str::upper(Str::random(6)),
            'code' => 'REC-'.Str::upper(Str::random(8)),
            'is_active' => true,
        ])->id;
    }

    private function userWithPermission(string $permissionCode): User
    {
        $permission = Permission::query()->firstOrCreate(
            ['code' => $permissionCode],
            [
                'name' => 'Correspondence test permission '.$permissionCode,
                'is_active' => true,
                'is_system' => true,
            ],
        );

        $role = Role::query()->create([
            'name' => 'Correspondence Test '.Str::upper(Str::random(8)),
            'code' => 'correspondence-test-'.Str::lower(Str::random(12)),
            'description' => 'Correspondence HTTP test role',
            'is_active' => true,
            'is_system' => false,
        ]);
        $role->permissions()->attach($permission->id, [
            'scope' => PermissionScope::defaultForPermission($permission->code)->value,
        ]);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);

        return $user->fresh();
    }
}
