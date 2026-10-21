<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Correspondence;

use App\Core\Permissions\Models\Permission;
use App\Core\Permissions\Enums\PermissionScope;
use App\Core\Roles\Models\Role;
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

        $this->actingAs($user)->post(route('correspondence.store'), [
            'type' => 'incoming',
            'subject' => '  نامه وارده  ',
            'composition_mode' => 'editor',
            'body' => '<p>متن <strong>مهم</strong><img src=x onerror=alert(1)></p>',
            'sender_name' => 'فرستنده',
        ])->assertRedirect(route('correspondence.index'));

        $this->assertDatabaseHas('correspondences', [
            'type' => 'incoming',
            'subject' => 'نامه وارده',
            'body' => '<p>متن <strong>مهم</strong></p>',
            'status' => 'draft',
            'registration_number' => null,
            'created_by' => $user->id,
        ]);
    }

    public function test_file_mode_requires_at_least_one_attachment(): void
    {
        $user = $this->userWithPermission('correspondence.create');

        $this->actingAs($user)->from(route('correspondence.create'))->post(route('correspondence.store'), [
            'type' => 'incoming',
            'subject' => 'نامه وارده',
            'composition_mode' => 'file',
        ])->assertRedirect(route('correspondence.create'))
            ->assertSessionHasErrors('attachments');

        $this->assertDatabaseCount('correspondences', 0);
    }

    public function test_user_can_create_file_only_draft_with_attachment(): void
    {
        $user = $this->userWithPermission('correspondence.create');
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
            'type' => 'incoming',
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

    public function test_user_without_correspondence_permission_is_forbidden(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->get(route('correspondence.index'))->assertForbidden();
    }

    private function userWithPermission(string $permissionCode): User
    {
        $permission = Permission::query()->firstOrCreate(
            ['code' => $permissionCode],
            [
                'name' => 'Correspondence test permission',
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
