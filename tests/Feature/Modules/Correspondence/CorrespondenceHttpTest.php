<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Correspondence;

use App\Core\Permissions\Models\Permission;
use App\Core\Permissions\Enums\PermissionScope;
use App\Core\Roles\Models\Role;
use App\Core\Departments\Models\Department;
use App\Core\ExternalParties\Models\ExternalOrganization;
use App\Core\ExternalParties\Models\ExternalDepartment;
use App\Core\Settings\Services\OrganizationDepartmentRoleService;
use App\Core\Settings\Services\SystemSettingsService;
use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
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

    public function test_correspondence_index_supports_mine_department_and_all_scopes(): void
    {
        $user = $this->userWithPermission('correspondence.view');
        $otherUser = $this->userWithPermission('correspondence.view');
        $department = Department::query()->create([
            'name' => 'واحد فیلتر مکاتبات '.Str::upper(Str::random(5)),
            'code' => 'FLT-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $user->departments()->attach($department->id, ['is_primary' => true, 'joined_at' => now()]);

        Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'مکاتبه شخصی',
            'status' => Correspondence::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);
        Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'مکاتبه واحد',
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => '1405/009990',
            'created_by' => $otherUser->id,
            'created_department_id' => $department->id,
            'current_department_id' => $department->id,
        ]);
        Correspondence::query()->create([
            'type' => Correspondence::TYPE_OUTGOING,
            'classification' => Correspondence::CLASSIFICATION_ORDINARY,
            'subject' => 'مکاتبه عمومی ثبت‌شده',
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => '1405/009991',
            'created_by' => $otherUser->id,
            'created_department_id' => $department->id,
        ]);

        $this->actingAs($user)->get(route('correspondence.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.scope', 'mine')
                ->where('correspondences.total', 1));

        $this->actingAs($user)->get(route('correspondence.index', ['scope' => 'department']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.scope', 'department')
                ->where('correspondences.total', 2));

        $this->actingAs($user)->get(route('correspondence.index', ['scope' => 'all']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.scope', 'all')
                ->where('correspondences.total', 3));
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

        $this->actingAs($viewer)->get(route('correspondence.index', ['scope' => 'all']))
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

    public function test_current_department_manager_can_add_multiple_copy_recipients_during_workflow(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $currentDepartment = Department::query()->create([
            'name' => 'واحد مسئول رونوشت '.Str::upper(Str::random(5)),
            'code' => 'CUR-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);
        $firstCopyDepartment = Department::query()->create([
            'name' => 'واحد رونوشت اول '.Str::upper(Str::random(5)),
            'code' => 'CP1-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $secondCopyDepartment = Department::query()->create([
            'name' => 'واحد رونوشت دوم '.Str::upper(Str::random(5)),
            'code' => 'CP2-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'افزودن رونوشت حین گردش',
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => '1405/009992',
            'created_by' => $manager->id,
            'current_department_id' => $currentDepartment->id,
            'workflow_state' => 'in_progress',
        ]);

        $this->actingAs($manager)
            ->post(route('correspondence.copies.store', $letter), [
                'department_ids' => [$firstCopyDepartment->id, $secondCopyDepartment->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('correspondence_copies', [
            'correspondence_id' => $letter->id,
            'department_id' => $firstCopyDepartment->id,
            'created_by' => $manager->id,
        ]);
        $this->assertDatabaseHas('correspondence_copies', [
            'correspondence_id' => $letter->id,
            'department_id' => $secondCopyDepartment->id,
            'created_by' => $manager->id,
        ]);
        $this->assertDatabaseCount('correspondence_actions', 2);
    }

    public function test_user_who_is_not_current_department_manager_cannot_add_workflow_copies(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $otherUser = $this->userWithPermission('correspondence.view');
        $currentDepartment = Department::query()->create([
            'name' => 'واحد مدیر نامه '.Str::upper(Str::random(5)),
            'code' => 'OWN-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);
        $copyDepartment = Department::query()->create([
            'name' => 'واحد مقصد رونوشت '.Str::upper(Str::random(5)),
            'code' => 'DST-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'رونوشت غیرمجاز',
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => '1405/009993',
            'created_by' => $manager->id,
            'current_department_id' => $currentDepartment->id,
            'workflow_state' => 'in_progress',
        ]);

        $this->actingAs($otherUser)
            ->post(route('correspondence.copies.store', $letter), [
                'department_ids' => [$copyDepartment->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('correspondence_copies', [
            'correspondence_id' => $letter->id,
            'department_id' => $copyDepartment->id,
        ]);
    }

    public function test_incoming_and_outgoing_letters_require_their_respective_dates(): void
    {
        $user = $this->userWithPermission('correspondence.create');

        $this->actingAs($user)->from(route('correspondence.create'))->post(route('correspondence.store'), [
            'type' => Correspondence::TYPE_OUTGOING,
            'subject' => 'نامه صادره بدون تاریخ',
            'composition_mode' => 'editor',
            'body' => '<p>متن نامه</p>',
        ])->assertRedirect(route('correspondence.create'))
            ->assertSessionHasErrors('sent_at');

        $secretariatUser = User::factory()->create(['status' => 'active']);
        $department = Department::query()->create([
            'name' => 'دبیرخانه تاریخ '.Str::upper(Str::random(5)),
            'code' => 'DAT-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $secretariatUser->id,
        ]);
        app(SystemSettingsService::class)->set([
            OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT) => (string) $department->id,
        ]);

        $this->actingAs($secretariatUser)->from(route('correspondence.create'))->post(route('correspondence.store'), [
            'type' => Correspondence::TYPE_INCOMING,
            'recipient_department_id' => $this->recipientDepartmentId(),
            'subject' => 'نامه وارده بدون تاریخ',
            'original_letters' => [UploadedFile::fake()->create('original.pdf', 10, 'application/pdf')],
        ])->assertRedirect(route('correspondence.create'))
            ->assertSessionHasErrors('received_at');

        $this->assertDatabaseMissing('correspondences', ['subject' => 'نامه صادره بدون تاریخ']);
        $this->assertDatabaseMissing('correspondences', ['subject' => 'نامه وارده بدون تاریخ']);

        $this->actingAs($user)->from(route('correspondence.create'))->post(route('correspondence.store'), [
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'نامه داخلی بدون واحد دریافت‌کننده',
            'composition_mode' => 'editor',
            'body' => '<p>متن نامه</p>',
        ])->assertRedirect(route('correspondence.create'))
            ->assertSessionHasErrors('recipient_department_id');

        $this->actingAs($secretariatUser)->from(route('correspondence.create'))->post(route('correspondence.store'), [
            'type' => Correspondence::TYPE_INCOMING,
            'received_at' => '2026-10-27',
            'subject' => 'نامه وارده بدون واحد دریافت‌کننده',
            'original_letters' => [UploadedFile::fake()->create('original.pdf', 10, 'application/pdf')],
        ])->assertRedirect(route('correspondence.create'))
            ->assertSessionHasErrors('recipient_department_id');

        $this->assertDatabaseMissing('correspondences', ['subject' => 'نامه داخلی بدون واحد دریافت‌کننده']);
        $this->assertDatabaseMissing('correspondences', ['subject' => 'نامه وارده بدون واحد دریافت‌کننده']);
    }

    public function test_related_correspondence_search_returns_numbers_dates_and_party_names(): void
    {
        $user = $this->userWithPermission('correspondence.create');
        $department = Department::query()->create([
            'name' => 'واحد جستجوی نامه',
            'code' => 'SRCH-'.Str::upper(Str::random(5)),
            'is_active' => true,
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_OUTGOING,
            'subject' => 'موضوع قابل جستجو',
            'status' => Correspondence::STATUS_REGISTERED,
            'letter_number' => '1405-000777',
            'registration_number' => 'REG-000777',
            'sent_at' => '2026-10-24',
            'registered_at' => now(),
            'created_by' => $user->id,
            'created_department_id' => $department->id,
        ]);

        $this->actingAs($user)->getJson(route('correspondence.related-search', ['q' => '000777']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $letter->id)
            ->assertJsonPath('data.0.letter_number', '1405-000777')
            ->assertJsonPath('data.0.registration_number', 'REG-000777')
            ->assertJsonPath('data.0.subject', 'موضوع قابل جستجو')
            ->assertJsonPath('data.0.created_department', 'واحد جستجوی نامه')
            ->assertJsonStructure(['data' => [['letter_date', 'external_registration_number']]]);
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

    public function test_user_with_multiple_department_memberships_can_choose_the_creator_department(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $primaryDepartment = Department::query()->create([
            'name' => 'واحد اصلی '.Str::upper(Str::random(5)),
            'code' => 'PRI-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $secondDepartment = Department::query()->create([
            'name' => 'واحد دوم '.Str::upper(Str::random(5)),
            'code' => 'SEC-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $user->departments()->attach($primaryDepartment->id, ['is_primary' => true, 'joined_at' => now()]);
        $user->departments()->attach($secondDepartment->id, ['is_primary' => false, 'joined_at' => now()]);

        $this->actingAs($user)->get(route('correspondence.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('createdDepartments', fn ($departments) => collect($departments)->pluck('id')->contains($primaryDepartment->id)
                    && collect($departments)->pluck('id')->contains($secondDepartment->id)));

        $this->actingAs($user)->post(route('correspondence.store'), [
            'type' => Correspondence::TYPE_INTERNAL,
            'created_department_id' => $secondDepartment->id,
            'recipient_department_id' => $primaryDepartment->id,
            'subject' => 'نامه از واحد دوم',
            'composition_mode' => 'editor',
            'body' => '<p>متن نامه</p>',
        ])->assertRedirect(route('correspondence.index'));

        $this->assertDatabaseHas('correspondences', [
            'subject' => 'نامه از واحد دوم',
            'created_by' => $user->id,
            'created_department_id' => $secondDepartment->id,
            'recipient_department_id' => $primaryDepartment->id,
        ]);
    }

    public function test_file_mode_requires_at_least_one_attachment(): void
    {
        $user = $this->userWithPermission('correspondence.create');

        $this->actingAs($user)->from(route('correspondence.create'))->post(route('correspondence.store'), [
            'type' => 'outgoing',
            'sent_at' => '2026-10-24',
            'subject' => 'نامه صادره',
            'composition_mode' => 'file',
        ])->assertRedirect(route('correspondence.create'))
            ->assertSessionHasErrors('attachments');

        $this->assertDatabaseCount('correspondences', 0);
    }

    public function test_scanner_upload_token_accepts_pdf_and_exposes_ready_status_only_to_its_owner(): void
    {
        Storage::fake('local');
        $owner = $this->userWithPermission('correspondence.create');
        $otherUser = $this->userWithPermission('correspondence.create');
        $token = Str::random(64);
        $cacheKey = 'correspondence_scanner_upload:'.hash('sha256', $token);

        Cache::put($cacheKey, [
            'user_id' => (int) $owner->id,
            'status' => 'pending',
        ], now()->addMinutes(10));

        $this->post(route('correspondence.scanner.upload', $token), [
            'file' => UploadedFile::fake()->create('scanned-letter.pdf', 10, 'application/pdf'),
        ])->assertOk()->assertJson(['status' => 'ready']);

        $this->actingAs($owner)->get(route('correspondence.scanner.status', $token))
            ->assertOk()
            ->assertJson(['status' => 'ready', 'file_name' => 'scanned-letter.pdf']);

        $this->actingAs($otherUser)->get(route('correspondence.scanner.status', $token))->assertNotFound();
    }

    public function test_incoming_correspondence_requires_an_original_letter_file_even_if_body_is_provided(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $department = Department::query()->create([
            'name' => 'دبیرخانه تست',
            'code' => 'SEC-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $user->id,
        ]);
        app(SystemSettingsService::class)->set([
            OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT) => (string) $department->id,
        ]);

        $this->actingAs($user)->from(route('correspondence.create'))->post(route('correspondence.store'), [
            'type' => Correspondence::TYPE_INCOMING,
            'received_at' => '2026-10-24',
            'subject' => 'نامه وارده بدون اصل نامه',
            'recipient_department_id' => $this->recipientDepartmentId(),
            'composition_mode' => 'editor',
            'body' => '<p>متن تایپی جایگزین اسکن نیست</p>',
        ])->assertRedirect(route('correspondence.create'))
            ->assertSessionHasErrors('original_letters');

        $this->assertDatabaseMissing('correspondences', ['subject' => 'نامه وارده بدون اصل نامه']);
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
        $this->actingAs($user)->post(route('correspondence.store'), ['type' => Correspondence::TYPE_OUTGOING, 'sent_at' => '2026-10-24', 'subject' => 'پیش‌نویس صادره مجاز', 'composition_mode' => 'editor', 'body' => '<p>متن</p>'])->assertRedirect(route('correspondence.index'));
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
        $this->assertDatabaseHas('correspondence_actions', [
            'correspondence_id' => $letter->id,
            'actor_id' => $creator->id,
            'action_type' => 'registered',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'correspondence.action.registered',
            'auditable_type' => $letter->getMorphClass(),
            'auditable_id' => $letter->id,
        ]);
    }

    public function test_configured_secretariat_can_create_incoming_letter_and_register_outgoing_draft(): void
    {
        $secretariatUser = User::factory()->create(['status' => 'active']);
        $department = Department::query()->create(['name' => 'دبیرخانه تست', 'code' => 'TEST-SEC', 'is_active' => true, 'manager_id' => $secretariatUser->id]);
        app(SystemSettingsService::class)->set([OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT) => (string) $department->id]);
        $this->actingAs($secretariatUser)->get(route('correspondence.create'))->assertOk()->assertInertia(fn ($page) => $page->where('types.0.value', Correspondence::TYPE_INCOMING));
        $this->actingAs($secretariatUser)->post(route('correspondence.store'), ['type' => Correspondence::TYPE_INCOMING, 'received_at' => '2026-10-24', 'recipient_department_id' => $this->recipientDepartmentId(), 'letter_number' => 'IN-1405-001', 'subject' => 'نامه وارده دبیرخانه', 'original_letters' => [UploadedFile::fake()->create('original.pdf', 10, 'application/pdf')]])->assertRedirect(route('correspondence.index'));
        $this->assertDatabaseHas('correspondences', ['type' => Correspondence::TYPE_INCOMING, 'subject' => 'نامه وارده دبیرخانه', 'created_by' => $secretariatUser->id]);
        $incomingLetter = Correspondence::query()->where('subject', 'نامه وارده دبیرخانه')->firstOrFail();
        $this->actingAs($secretariatUser)->get(route('correspondence.show', $incomingLetter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('originalLetters.0.file_name', 'original.pdf')
                ->where('attachments', []));
        app(SystemSettingsService::class)->set(['correspondence.approval.outgoing' => 'direct']);
        $outgoingCreator = User::factory()->create(['status' => 'active']);
        $letter = Correspondence::query()->create(['type' => Correspondence::TYPE_OUTGOING, 'subject' => 'پیش‌نویس صادره برای دبیرخانه', 'status' => Correspondence::STATUS_DRAFT, 'created_by' => $outgoingCreator->id]);
        $this->actingAs($secretariatUser)->post(route('correspondence.register', $letter))->assertRedirect();
        $this->assertDatabaseHas('correspondences', ['id' => $letter->id, 'status' => Correspondence::STATUS_REGISTERED, 'registered_by' => $secretariatUser->id, 'workflow_state' => 'awaiting_dispatch']);

        $this->actingAs($secretariatUser)->put(route('correspondence.external-tracking.update', $letter), [
            'sent_at' => '2026-10-27 10:30:00',
            'dispatch_method' => 'courier',
        ])->assertRedirect();

        $this->assertDatabaseHas('correspondences', ['id' => $letter->id, 'workflow_state' => 'dispatched', 'sent_at' => '2026-10-27 10:30:00']);
        $this->assertDatabaseHas('correspondence_actions', ['correspondence_id' => $letter->id, 'action_type' => 'dispatched', 'actor_id' => $secretariatUser->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'correspondence.action.dispatched', 'auditable_type' => $letter->getMorphClass(), 'auditable_id' => $letter->id]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $outgoingCreator->id, 'type' => 'App\\Modules\\Notifications\\Notifications\\CorrespondenceNotification']);
        $notification = \Illuminate\Support\Facades\DB::table('notifications')
            ->where('notifiable_id', $outgoingCreator->id)
            ->where('type', 'App\\Modules\\Notifications\\Notifications\\CorrespondenceNotification')
            ->first();
        $this->assertNotNull($notification);
        $this->assertStringNotContainsString('پیش‌نویس صادره برای دبیرخانه', (string) $notification->data);
    }
    public function test_outgoing_correspondence_creator_can_update_tracking_but_cannot_finalize_dispatch(): void
    {
        $creator = User::factory()->create(['status' => 'active']);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_OUTGOING,
            'subject' => 'نامه صادره برای پیگیری',
            'status' => Correspondence::STATUS_DRAFT,
            'created_by' => $creator->id,
        ]);

        $this->actingAs($creator)->put(route('correspondence.external-tracking.update', $letter), [
            'sent_at' => '2026-10-24',
        ])->assertForbidden();

        $this->actingAs($creator)->put(route('correspondence.external-tracking.update', $letter), [
            'task_id' => null,
            'external_registration_number' => 'IN-1405-184',
            'external_registered_at' => '2026-10-25',
            'dispatch_method' => 'official_portal',
            'external_tracking_notes' => 'نامه در دبیرخانه سازمان مقصد ثبت شد.',
        ])->assertRedirect();

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'external_registration_number' => 'IN-1405-184',
            'external_registered_at' => '2026-10-25',
            'dispatch_method' => 'official_portal',
            'external_tracking_notes' => 'نامه در دبیرخانه سازمان مقصد ثبت شد.',
            'sent_at' => null,
        ]);
    }

    public function test_external_tracking_cannot_be_updated_for_an_incoming_letter(): void
    {
        $creator = User::factory()->create(['status' => 'active']);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'subject' => 'نامه وارده بدون پیگیری صادره',
            'status' => Correspondence::STATUS_DRAFT,
            'created_by' => $creator->id,
        ]);

        $this->actingAs($creator)->put(route('correspondence.external-tracking.update', $letter), [
            'external_registration_number' => 'IN-1405-185',
        ])->assertSessionHasErrors('external_tracking');

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'external_registration_number' => null,
        ]);
    }

    public function test_current_department_manager_can_approve_an_outgoing_draft_before_registration(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $department = Department::query()->create([
            'name' => 'واحد تأییدکننده '.Str::upper(Str::random(5)),
            'code' => 'APR-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $manager->id,
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_OUTGOING,
            'subject' => 'نامه منتظر تأیید مدیر',
            'status' => Correspondence::STATUS_DRAFT,
            'workflow_state' => 'awaiting_manager_approval',
            'current_department_id' => $department->id,
            'current_user_id' => $manager->id,
            'created_department_id' => $department->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'manager_approve',
            'note' => 'محتوا بررسی شد.',
        ])->assertRedirect();

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'workflow_state' => 'approved_for_registration',
        ]);
        $this->assertDatabaseHas('correspondence_actions', [
            'correspondence_id' => $letter->id,
            'action_type' => 'manager_approve',
            'actor_id' => $manager->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'correspondence.action.manager_approve',
            'auditable_type' => $letter->getMorphClass(),
            'auditable_id' => $letter->id,
        ]);
    }

    public function test_registered_confidential_letter_is_visible_to_copy_department_members(): void
    {
        $creator = $this->userWithPermission('correspondence.create');
        $viewer = $this->userWithPermission('correspondence.view');
        $copyDepartment = Department::query()->create([
            'name' => 'واحد رونوشت '.Str::upper(Str::random(5)),
            'code' => 'CC-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $viewer->departments()->attach($copyDepartment->id, ['is_primary' => true, 'joined_at' => now()]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'classification' => Correspondence::CLASSIFICATION_CONFIDENTIAL,
            'subject' => 'نامه محرمانه دارای رونوشت',
            'status' => Correspondence::STATUS_REGISTERED,
            'created_by' => $creator->id,
        ]);
        $letter->copies()->attach($copyDepartment->id, ['created_by' => $creator->id, 'note' => 'جهت اطلاع']);

        $this->actingAs($viewer)->get(route('correspondence.show', $letter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('correspondence.subject', 'نامه محرمانه دارای رونوشت')
                ->where('correspondence.copies.0.name', $copyDepartment->name));

        $targetDepartment = Department::query()->create([
            'name' => 'واحد مقصد آزمون '.Str::upper(Str::random(5)),
            'code' => 'DST-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $this->actingAs($viewer)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'manager_refer',
            'to_department_id' => $targetDepartment->id,
            'note' => 'نباید از طریق رونوشت قابل اقدام باشد.',
        ])->assertForbidden();
    }

    public function test_employee_must_submit_a_documented_result_and_manager_confirms_it_before_completion(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $employee = $this->userWithPermission('correspondence.view');
        $department = Department::query()->create([
            'name' => 'واحد نتیجه '.Str::upper(Str::random(5)),
            'code' => 'RES-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $manager->id,
        ]);
        $employee->departments()->attach($department->id, ['is_primary' => true, 'joined_at' => now()]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'classification' => Correspondence::CLASSIFICATION_ORDINARY,
            'subject' => 'نامه نیازمند اعلام نتیجه',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'in_progress',
            'current_department_id' => $department->id,
            'current_user_id' => $manager->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($employee)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'submit_result',
            'note' => '',
        ])->assertSessionHasErrors('workflow_action');

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'workflow_state' => 'in_progress',
        ]);

        $this->actingAs($employee)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'submit_result',
            'note' => 'بررسی انجام شد؛ درخواست به علت نبود اعتبار قابل انجام نیست.',
        ])->assertRedirect();

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'workflow_state' => 'result_pending_manager',
            'current_user_id' => $manager->id,
        ]);
        $this->assertDatabaseHas('correspondence_actions', [
            'correspondence_id' => $letter->id,
            'action_type' => 'submit_result',
            'actor_id' => $employee->id,
            'note' => 'بررسی انجام شد؛ درخواست به علت نبود اعتبار قابل انجام نیست.',
        ]);

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'confirm_result',
            'note' => '',
        ])->assertSessionHasErrors('workflow_action');

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'confirm_result',
            'note' => 'نتیجه و علت اعلام‌شده بررسی و تأیید شد.',
        ])->assertRedirect();

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'workflow_state' => 'completed_with_result',
            'current_user_id' => null,
        ]);
    }

    public function test_legacy_reject_and_close_without_result_actions_are_not_accepted(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $department = Department::query()->create([
            'name' => 'واحد آزمون گردش '.Str::upper(Str::random(5)),
            'code' => 'WF-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $manager->id,
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'نامه بدون اقدام منسوخ',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'in_progress',
            'current_department_id' => $department->id,
            'current_user_id' => $manager->id,
            'created_by' => $manager->id,
        ]);

        foreach (['reject_request', 'close_without_result'] as $legacyAction) {
            $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
                'action_type' => $legacyAction,
                'note' => 'این اقدام دیگر نباید پذیرفته شود.',
            ])->assertSessionHasErrors('action_type');
        }

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'workflow_state' => 'in_progress',
        ]);
    }

    private function recipientDepartmentId(): int
    {
        return Department::query()->create([
            'name' => 'واحد گیرنده '.Str::upper(Str::random(6)),
            'code' => 'REC-'.Str::upper(Str::random(8)),
            'is_active' => true,
        ])->id;
    }

    public function test_incoming_letter_requires_and_preserves_original_letter_number(): void
    {
        Storage::fake('local');
        $secretariat=User::factory()->create(['status'=>'active']);
        $department=Department::query()->create(['name'=>'دبیرخانه شماره نامه '.Str::upper(Str::random(5)),'code'=>'IN-'.Str::upper(Str::random(5)),'is_active'=>true,'manager_id'=>$secretariat->id]);
        app(SystemSettingsService::class)->set([OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT)=>(string)$department->id]);
        $payload=['type'=>Correspondence::TYPE_INCOMING,'recipient_department_id'=>$this->recipientDepartmentId(),'received_at'=>'2026-10-27','subject'=>'وارده با شماره اصلی','letter_number'=>'OUTSIDE-452/الف','original_letters'=>[UploadedFile::fake()->create('original.pdf',10,'application/pdf')]];
        $this->actingAs($secretariat)->post(route('correspondence.store'),$payload)->assertRedirect(route('correspondence.index'));
        $this->assertDatabaseHas('correspondences',['subject'=>'وارده با شماره اصلی','letter_number'=>'OUTSIDE-452/الف','registration_number'=>null]);
        unset($payload['letter_number']); $payload['subject']='وارده بدون شماره اصلی';
        $this->actingAs($secretariat)->from(route('correspondence.create'))->post(route('correspondence.store'),$payload)->assertRedirect(route('correspondence.create'))->assertSessionHasErrors('letter_number');
        $this->assertDatabaseMissing('correspondences',['subject'=>'وارده بدون شماره اصلی']);
    }

    public function test_secretariat_can_edit_and_delete_only_before_any_action(): void
    {
        $secretariat=User::factory()->create(['status'=>'active']);
        $department=Department::query()->create(['name'=>'دبیرخانه ویرایش '.Str::upper(Str::random(5)),'code'=>'ED-'.Str::upper(Str::random(5)),'is_active'=>true,'manager_id'=>$secretariat->id]);
        app(SystemSettingsService::class)->set([OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT)=>(string)$department->id]);
        $recipient=$this->recipientDepartmentId();
        $letter=Correspondence::query()->create(['type'=>Correspondence::TYPE_INCOMING,'subject'=>'موضوع اولیه','letter_number'=>'IN-123','status'=>Correspondence::STATUS_DRAFT,'created_by'=>$secretariat->id,'recipient_department_id'=>$recipient,'received_at'=>'2026-10-27']);
        $this->actingAs($secretariat)->put(route('correspondence.update',$letter),['subject'=>'موضوع اصلاح‌شده','classification'=>'ordinary','letter_number'=>'IN-124','received_at'=>'2026-10-27','recipient_department_id'=>$recipient])->assertRedirect(route('correspondence.index'));
        $this->assertDatabaseHas('correspondences',['id'=>$letter->id,'subject'=>'موضوع اصلاح‌شده','letter_number'=>'IN-124']);
        $this->actingAs($secretariat)->delete(route('correspondence.destroy',$letter))->assertRedirect(route('correspondence.index'));
        $this->assertDatabaseMissing('correspondences',['id'=>$letter->id]);
        $blocked=Correspondence::query()->create(['type'=>Correspondence::TYPE_INCOMING,'subject'=>'نامه دارای اقدام','letter_number'=>'IN-125','status'=>Correspondence::STATUS_DRAFT,'created_by'=>$secretariat->id]);
        \App\Modules\Correspondence\Models\CorrespondenceAction::query()->create(['correspondence_id'=>$blocked->id,'actor_id'=>$secretariat->id,'action_type'=>'manager_approve','note'=>'اقدام آزمایشی']);
        $this->actingAs($secretariat)->delete(route('correspondence.destroy',$blocked))->assertForbidden();
        $this->assertDatabaseHas('correspondences',['id'=>$blocked->id]);
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
    public function test_outgoing_tracking_updates_are_audited_without_storing_tracking_note_values(): void
    {
        $user = $this->userWithPermission('correspondence.create');
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_OUTGOING,
            'classification' => Correspondence::CLASSIFICATION_ORDINARY,
            'subject' => 'نامه صادره برای پیگیری',
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => '1405/000321',
            'created_by' => $user->id,
        ]);
        $privateNote = 'جزئیات داخلی پیگیری که نباید در Audit ذخیره شود';

        $this->actingAs($user)->put(route('correspondence.external-tracking.update', $letter), [
            'sent_at' => '2026-10-26',
        ])->assertForbidden();

        $this->actingAs($user)->put(route('correspondence.external-tracking.update', $letter), [
            'external_registration_number' => 'EXT-991',
            'external_registered_at' => '2026-10-25',
            'dispatch_method' => 'courier',
            'external_tracking_notes' => $privateNote,
        ])->assertRedirect();

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'external_registration_number' => 'EXT-991',
            'dispatch_method' => 'courier',
            'external_tracking_notes' => $privateNote,
        ]);

        $audit = \Illuminate\Support\Facades\DB::table('audit_logs')
            ->where('event', 'correspondence.external_tracking.updated')
            ->where('auditable_type', $letter->getMorphClass())
            ->where('auditable_id', $letter->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringContainsString('external_tracking_notes', (string) $audit->metadata);
        $this->assertStringNotContainsString($privateNote, json_encode($audit, JSON_UNESCAPED_UNICODE));
        $this->assertNull($audit->old_values);
        $this->assertNull($audit->new_values);
    }

    public function test_cartable_includes_actionable_correspondence_but_not_copy_only_access(): void
    {
        $actor = $this->userWithPermission('correspondence.view');
        $copyViewer = $this->userWithPermission('correspondence.view');
        $currentDepartment = Department::query()->create([
            'name' => 'واحد جاری '.Str::upper(Str::random(5)),
            'code' => 'CUR-'.Str::upper(Str::random(6)),
            'manager_id' => $actor->id,
            'is_active' => true,
        ]);
        $copyDepartment = Department::query()->create([
            'name' => 'واحد رونوشت '.Str::upper(Str::random(5)),
            'code' => 'CPY-'.Str::upper(Str::random(6)),
            'manager_id' => $copyViewer->id,
            'is_active' => true,
        ]);

        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'classification' => Correspondence::CLASSIFICATION_CONFIDENTIAL,
            'subject' => 'نامه نیازمند اقدام',
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => '1405/008888',
            'created_by' => $actor->id,
            'current_department_id' => $currentDepartment->id,
            'workflow_state' => 'in_progress',
        ]);
        $letter->copies()->attach($copyDepartment->id, [
            'created_by' => $actor->id,
            'note' => 'جهت اطلاع',
        ]);

        $actorCartable = app(\App\Modules\Cartable\Services\CartableService::class)->for($actor);
        $copyCartable = app(\App\Modules\Cartable\Services\CartableService::class)->for($copyViewer);

        $this->assertSame(1, $actorCartable['summary']['correspondence_referrals']);
        $this->assertSame($letter->id, $actorCartable['correspondenceItems']->first()->id);
        $this->assertSame(0, $copyCartable['summary']['correspondence_referrals']);
        $this->assertCount(0, $copyCartable['correspondenceItems']);
    }

    public function test_outgoing_correspondence_can_select_an_existing_external_organization_and_department(): void
    {
        $user = $this->userWithPermission('correspondence.create');
        $organization = ExternalOrganization::query()->create([
            'name' => 'سازمان بیرونی '.Str::upper(Str::random(5)),
            'status' => 'active',
        ]);
        $department = ExternalDepartment::query()->create([
            'external_organization_id' => $organization->id,
            'name' => 'واحد بیرونی '.Str::upper(Str::random(5)),
            'status' => 'active',
        ]);

        $this->actingAs($user)->get(route('correspondence.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('externalOrganizations.0.id', $organization->id)
                ->where('externalDepartments.0.id', $department->id));

        $this->actingAs($user)->post(route('correspondence.store'), [
            'type' => Correspondence::TYPE_OUTGOING,
            'sent_at' => '2026-10-24',
            'external_organization_id' => $organization->id,
            'external_department_id' => $department->id,
            'recipient_name' => 'مدیر واحد',
            'subject' => 'نامه به سازمان بیرونی',
            'composition_mode' => 'editor',
            'body' => '<p>متن نامه</p>',
        ])->assertRedirect(route('correspondence.index'));

        $letter = Correspondence::query()->where('subject', 'نامه به سازمان بیرونی')->firstOrFail();
        $this->assertSame($organization->id, (int) $letter->external_organization_id);
        $this->assertSame($department->id, (int) $letter->external_department_id);

        $this->actingAs($user)->get(route('correspondence.show', $letter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('correspondence.external_organization.name', $organization->name)
                ->where('correspondence.external_department.name', $department->name));
    }

    public function test_correspondence_rejects_external_department_from_another_organization(): void
    {
        $user = $this->userWithPermission('correspondence.create');
        $organization = ExternalOrganization::query()->create([
            'name' => 'سازمان الف '.Str::upper(Str::random(5)),
            'status' => 'active',
        ]);
        $otherOrganization = ExternalOrganization::query()->create([
            'name' => 'سازمان ب '.Str::upper(Str::random(5)),
            'status' => 'active',
        ]);
        $department = ExternalDepartment::query()->create([
            'external_organization_id' => $otherOrganization->id,
            'name' => 'واحد متعلق به سازمان ب',
            'status' => 'active',
        ]);

        $this->actingAs($user)->from(route('correspondence.create'))->post(route('correspondence.store'), [
            'type' => Correspondence::TYPE_OUTGOING,
            'external_organization_id' => $organization->id,
            'external_department_id' => $department->id,
            'subject' => 'نامه با واحد نامعتبر',
            'composition_mode' => 'editor',
            'body' => '<p>متن نامه</p>',
        ])->assertRedirect(route('correspondence.create'))
            ->assertSessionHasErrors('external_department_id');

        $this->assertDatabaseMissing('correspondences', ['subject' => 'نامه با واحد نامعتبر']);
    }


}
