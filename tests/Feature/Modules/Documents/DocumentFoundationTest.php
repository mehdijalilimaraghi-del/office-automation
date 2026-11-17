<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Documents;

use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Services\DocumentService;
use App\Modules\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CorePermissionSeeder::class);
        Storage::fake();
    }

    public function test_document_creation_stores_first_version_and_links_to_task(): void
    {
        $creator = $this->userWithPermissions(['documents.view', 'documents.create']);
        $task = Task::query()->create([
            'title' => 'Document task',
            'status' => 'todo',
            'priority' => 'normal',
            'created_by' => $creator->id,
            'assignee_id' => $creator->id,
            'is_active' => true,
        ]);

        $document = app(DocumentService::class)->create(
            [
                'title' => 'مدرک قرارداد',
                'document_type' => 'contract',
                'description' => 'نسخه اولیه',
            ],
            UploadedFile::fake()->create('contract.pdf', 128, 'application/pdf'),
            $creator,
            $task,
            'task_input',
        );

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'title' => 'مدرک قرارداد',
            'created_by' => $creator->id,
        ]);

        $this->assertDatabaseHas('document_versions', [
            'document_id' => $document->id,
            'version_number' => 1,
            'original_name' => 'contract.pdf',
            'uploaded_by' => $creator->id,
        ]);

        $this->assertDatabaseHas('document_links', [
            'document_id' => $document->id,
            'linkable_type' => $task->getMorphClass(),
            'linkable_id' => $task->id,
            'purpose' => 'task_input',
        ]);

        Storage::assertExists($document->versions->first()->path);
    }

    public function test_same_document_can_be_linked_to_multiple_tasks_without_duplicate_file(): void
    {
        $creator = $this->userWithPermissions(['documents.view', 'documents.create']);
        $firstTask = $this->createTask($creator, 'First task');
        $secondTask = $this->createTask($creator, 'Second task');

        $service = app(DocumentService::class);
        $document = $service->create(
            ['title' => 'Shared evidence'],
            UploadedFile::fake()->create('evidence.pdf', 64, 'application/pdf'),
            $creator,
            $firstTask,
        );

        $service->link($document, $secondTask, $creator, 'handoff');

        $this->assertSame(2, $document->refresh()->links()->count());
        $this->assertSame(1, $document->versions()->count());
        $this->assertCount(1, Storage::disk(config('filesystems.default'))->allFiles('documents/'.$document->id));
    }

    public function test_new_document_version_keeps_previous_version_and_increments_version_number(): void
    {
        $creator = $this->userWithPermissions(['documents.view', 'documents.create']);
        $document = app(DocumentService::class)->create(
            ['title' => 'Versioned document'],
            UploadedFile::fake()->create('v1.pdf', 32, 'application/pdf'),
            $creator,
        );

        $version = app(DocumentService::class)->addVersion(
            $document,
            UploadedFile::fake()->create('v2.pdf', 48, 'application/pdf'),
            $creator,
        );

        $this->assertSame(2, $version->version_number);
        $this->assertSame(2, $document->refresh()->versions()->count());
        $this->assertDatabaseHas('document_versions', [
            'document_id' => $document->id,
            'version_number' => 1,
            'original_name' => 'v1.pdf',
        ]);
        $this->assertDatabaseHas('document_versions', [
            'document_id' => $document->id,
            'version_number' => 2,
            'original_name' => 'v2.pdf',
        ]);
    }

    public function test_deleting_document_removes_database_records_and_all_version_files(): void
    {
        $creator = $this->userWithPermissions(['documents.view', 'documents.create', 'documents.delete']);
        $task = $this->createTask($creator, 'Delete document task');

        $service = app(DocumentService::class);
        $document = $service->create(
            ['title' => 'Document to delete'],
            UploadedFile::fake()->create('v1.pdf', 16, 'application/pdf'),
            $creator,
            $task,
        );

        $versionTwo = $service->addVersion(
            $document,
            UploadedFile::fake()->create('v2.pdf', 24, 'application/pdf'),
            $creator,
        );

        $firstVersion = $document->refresh()->versions()->where('version_number', 1)->firstOrFail();
        $this->assertTrue($creator->can('delete', $document));
        $this->assertTrue(Storage::disk(config('filesystems.default'))->exists($firstVersion->path));
        $this->assertTrue(Storage::disk(config('filesystems.default'))->exists($versionTwo->path));

        $service->delete($document);

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        $this->assertDatabaseMissing('document_versions', ['document_id' => $document->id]);
        $this->assertDatabaseMissing('document_links', ['document_id' => $document->id]);
        $this->assertDatabaseMissing('document_accesses', ['document_id' => $document->id]);
        Storage::disk(config('filesystems.default'))->assertMissing($firstVersion->path);
        Storage::disk(config('filesystems.default'))->assertMissing($versionTwo->path);
    }

    public function test_explicit_document_access_allows_view_without_being_creator(): void
    {
        $creator = $this->userWithPermissions(['documents.view', 'documents.create']);
        $viewer = $this->userWithPermissions(['documents.view']);
        $document = app(DocumentService::class)->create(
            ['title' => 'Protected document'],
            UploadedFile::fake()->create('protected.pdf', 16, 'application/pdf'),
            $creator,
        );

        $this->assertFalse($viewer->can('view', $document));

        app(DocumentService::class)->grantAccess($document, $viewer, $creator);

        $this->assertTrue($viewer->can('view', $document));
    }

    public function test_archive_registers_a_physical_original_and_limits_search_and_download_to_authorized_users(): void
    {
        $creator = $this->userWithPermissions(['documents.view', 'documents.create']);
        $other = $this->userWithPermissions(['documents.view']);
        $attachment = app(DocumentService::class)->create(
            ['title' => 'Archive match outside the archive'],
            UploadedFile::fake()->create('attachment.pdf', 5, 'application/pdf'),
            $creator,
        );
        $attributes = [
            'title' => 'Archive match 123',
            'document_type' => 'Contract',
            'archive_kind' => 'physical',
            'retain_until' => '2029-03-20',
        ];

        $this->actingAs($creator)->post(route('archive.store'), [
            ...$attributes,
            'file' => UploadedFile::fake()->create('original.pdf', 5, 'application/pdf'),
        ])->assertSessionHasErrors('archive_location');

        $this->actingAs($creator)->post(route('archive.store'), [
            ...$attributes,
            'archive_location' => 'Building A / Shelf 2',
            'file' => UploadedFile::fake()->create('original.pdf', 5, 'application/pdf'),
        ])->assertRedirect(route('archive.index'))->assertSessionHas('success');

        $record = Document::query()->where('title', $attributes['title'])->firstOrFail();
        $this->assertSame('physical', $record->archive_kind);
        $this->assertSame('Building A / Shelf 2', $record->archive_location);
        $this->assertSame(1, $record->versions()->count());
        $this->assertNull($attachment->refresh()->archive_kind);

        $this->get(route('archive.index', ['search' => 'Archive match', 'kind' => 'physical']))
            ->assertInertia(fn ($page) => $page->component('Archive/Index')
                ->has('records.data', 1)->where('records.data.0.id', $record->id));
        $this->get(route('documents.download', $record))->assertOk();

        $this->actingAs($other)->get(route('archive.index', ['search' => 'Archive match']))
            ->assertInertia(fn ($page) => $page->has('records.data', 0)->where('canCreate', false));
        $this->get(route('documents.download', $record))->assertForbidden();
        $this->post(route('archive.store'), $attributes)->assertForbidden();

        app(DocumentService::class)->grantAccess($record, $other, $creator);
        $this->get(route('archive.index'))->assertInertia(fn ($page) => $page->has('records.data', 1));
        $this->get(route('documents.download', $record))->assertOk();
        $this->actingAs($this->userWithPermissions([]))->get(route('archive.index'))->assertForbidden();
    }

    public function test_archive_scan_uses_installed_helper_callback_but_is_scoped_to_archive_and_owner(): void
    {
        Storage::fake('local');
        $user = $this->userWithPermissions(['documents.view', 'documents.create']);
        $other = $this->userWithPermissions(['documents.view', 'documents.create']);
        $session = $this->actingAs($user)->postJson(route('archive.scanner.start'))->assertOk()->json();

        $this->assertSame(route('correspondence.scanner.upload', $session['token']), $session['upload_url']);
        $this->postJson($session['upload_url'], [
            'file' => UploadedFile::fake()->create('scan.pdf', 5, 'application/pdf'),
        ])->assertOk();
        $this->actingAs($other)->getJson($session['status_url'])->assertNotFound();
        $this->actingAs($user)->getJson($session['status_url'])->assertJsonPath('status', 'ready');
        $this->get(route('archive.scanner.preview', $session['token']))->assertOk();
        $this->assertNull(app(\App\Modules\Documents\Services\ScannerStagingService::class)
            ->readyUpload('correspondence', $session['token'], $user));

        $payload = [
            'title' => 'NAPS2 archive',
            'document_type' => 'Contract',
            'archive_kind' => 'digital',
            'scanner_upload_token' => $session['token'],
        ];

        $this->post(route('archive.store'), $payload)->assertRedirect(route('archive.index'))->assertSessionHas('success');
        $this->post(route('archive.store'), $payload)->assertSessionHasErrors('scanner_upload_token');
        $this->assertSame(1, Document::query()->where('title', 'NAPS2 archive')->count());
    }

    public function test_archive_retains_versions_and_records_custody_only_in_valid_order(): void
    {
        $permissions = ['documents.view', 'documents.create', 'documents.update'];
        $creator = $this->userWithPermissions($permissions);
        $reviewer = $this->userWithPermissions($permissions);
        $viewer = $this->userWithPermissions($permissions);

        $this->actingAs($creator)->post(route('archive.store'), [
            'title' => 'Custody test record',
            'document_type' => 'Contract',
            'archive_kind' => 'physical',
            'archive_location' => 'Shelf 1',
            'file' => UploadedFile::fake()->create('first.pdf', 5, 'application/pdf'),
        ])->assertRedirect(route('archive.index'));
        $record = Document::query()->where('title', 'Custody test record')->firstOrFail();
        $documents = app(DocumentService::class);
        $documents->grantAccess($record, $reviewer, $creator, 'edit');
        $documents->grantAccess($record, $viewer, $creator);

        $this->post(route('archive.versions.store', $record), [
            'file' => UploadedFile::fake()->create('second.pdf', 5, 'application/pdf'),
        ])->assertSessionHas('success');
        $this->get(route('archive.show', $record))->assertInertia(fn ($page) => $page
            ->component('Archive/Show')->has('versions', 2)->where('versions.0.version_number', 2));
        $this->get(route('documents.download', ['document' => $record, 'version' => 1]))
            ->assertDownload('first.pdf');
        $this->actingAs($viewer)->post(route('archive.versions.store', $record))->assertForbidden();

        $url = route('archive.custody.store', $record);
        $this->post($url, ['action' => 'checkout'])->assertForbidden();
        $this->actingAs($creator)->post($url, ['action' => 'checkout'])->assertSessionHasErrors('action');
        $this->post($url, [
            'action' => 'request', 'recipient' => 'Records office', 'note' => 'Verification',
            'due_on' => now()->addDays(7)->toDateString(),
        ])->assertSessionHas('success');
        $cartable = app(\App\Modules\Cartable\Services\CartableService::class);
        $this->assertSame([], $cartable->for($creator)['archiveApprovals']);
        $this->assertSame([], $cartable->for($viewer)['archiveApprovals']);
        $this->assertSame([$record->id], array_column($cartable->for($reviewer)['archiveApprovals'], 'id'));
        $this->post($url, ['action' => 'approve'])->assertSessionHasErrors('action');
        $this->actingAs($reviewer)->post($url, ['action' => 'approve'])->assertSessionHas('success');
        $this->assertSame('Shelf 1', $record->refresh()->archive_location);
        $this->post($url, ['action' => 'checkout'])->assertSessionHas('success');
        $this->post($url, ['action' => 'checkout'])->assertSessionHasErrors('action');
        $this->get(route('archive.show', $record))->assertInertia(fn ($page) => $page
            ->where('custodyCurrent.action', 'checkout')->where('custodyCurrent.recipient', 'Records office'));
        $this->post($url, ['action' => 'return', 'location' => 'Shelf 2'])->assertSessionHas('success');
        $this->assertSame('Shelf 2', $record->refresh()->archive_location);
        $this->assertSame(['request', 'approve', 'checkout', 'return'], $record->custodyEvents()->orderBy('id')->pluck('action')->all());
    }

    public function test_archive_category_policy_requires_settings_permission_and_is_snapshotted_on_request(): void
    {
        $owner = $this->userWithPermissions(['documents.view', 'documents.create', 'documents.update']);
        $administrator = $this->userWithPermissions(['documents.view', 'settings.manage']);
        $policy = route('archive.approval-policy.update');

        $this->actingAs($owner)->put($policy, ['categories' => 'Routine'])->assertForbidden();
        $this->actingAs($administrator)->put($policy, ['categories' => " ROUTINE \nRoutine"])
            ->assertSessionHas('success');
        $this->get(route('archive.index'))->assertInertia(fn ($page) => $page
            ->where('approvalExceptions', ['routine']));

        $this->actingAs($owner)->post(route('archive.store'), [
            'title' => 'Exempt archive record',
            'document_type' => 'Routine',
            'archive_kind' => 'physical',
            'archive_location' => 'Shelf A',
            'file' => UploadedFile::fake()->create('document.pdf', 5, 'application/pdf'),
        ])->assertSessionHas('success');
        $record = Document::query()->where('title', 'Exempt archive record')->firstOrFail();
        $url = route('archive.custody.store', $record);
        $this->post($url, [
            'action' => 'request', 'recipient' => 'Records', 'note' => 'Inspection',
            'due_on' => now()->addDays(3)->toDateString(),
        ])->assertSessionHas('success');
        $this->assertFalse($record->latestCustodyEvent()->firstOrFail()->approval_required);
        $this->actingAs($administrator)->put($policy, ['categories' => ''])->assertSessionHas('success');
        $this->actingAs($owner)->post($url, ['action' => 'checkout'])->assertSessionHas('success');
        $this->assertSame('checkout', $record->latestCustodyEvent()->firstOrFail()->action);
    }

    public function test_archive_location_catalog_and_references_preserve_document_and_target_permissions(): void
    {
        $owner = $this->userWithPermissions(['documents.view', 'documents.create', 'documents.update', 'tasks.view', 'correspondence.view']);
        $administrator = $this->userWithPermissions(['documents.view', 'settings.manage']);
        $viewer = $this->userWithPermissions(['documents.view', 'documents.update']);
        $settings = route('archive.locations.update');
        $this->actingAs($owner)->put($settings, ['locations' => 'Archive / Room'])->assertForbidden();
        $this->actingAs($administrator)->put($settings, ['locations' => 'Invalid'])->assertSessionHasErrors('locations');
        $this->put($settings, ['locations' => "Archive/Room/Shelf 1\nArchive / Room / Shelf 2"])
            ->assertSessionHas('success');

        $attributes = [
            'title' => 'Catalog record',
            'document_type' => 'Contract',
            'archive_kind' => 'physical',
        ];
        $this->actingAs($owner)->post(route('archive.store'), [
            ...$attributes,
            'archive_location' => 'Unlisted',
            'file' => UploadedFile::fake()->create('archive.pdf', 5, 'application/pdf'),
        ])->assertSessionHasErrors('archive_location');
        $this->post(route('archive.store'), [
            ...$attributes,
            'archive_location' => 'Archive / Room / Shelf 1',
            'file' => UploadedFile::fake()->create('archive.pdf', 5, 'application/pdf'),
        ])->assertSessionHas('success');

        $record = Document::query()->where('title', $attributes['title'])->firstOrFail();
        $custody = route('archive.custody.store', $record);
        $this->post($custody, ['action' => 'move', 'location' => 'Unlisted'])->assertSessionHasErrors('location');
        $this->post($custody, ['action' => 'move', 'location' => 'Archive / Room / Shelf 2'])->assertSessionHas('success');

        $task = $this->createTask($owner, 'Linked task');
        $url = route('archive.references.store', $record);
        $payload = ['target_type' => 'task', 'target_id' => $task->id];
        $this->post($url, $payload)->assertSessionHas('success');
        $this->post($url, $payload)->assertSessionHas('success');
        $this->assertSame(1, $record->archiveReferences()->count());
        $this->assertSame(1, $record->versions()->count());
        $this->get(route('archive.show', $record))->assertInertia(fn ($page) => $page
            ->has('references', 1)->where('references.0.id', $task->id));

        $letter = \App\Modules\Correspondence\Models\Correspondence::query()->create([
            'type' => 'outgoing', 'subject' => 'Linked correspondence', 'status' => 'draft',
            'created_by' => $owner->id,
        ]);
        $definition = \App\Modules\Workflows\Models\WorkflowDefinition::query()->create([
            'name' => 'Linked workflow', 'code' => 'archive-workflow', 'created_by' => $owner->id, 'is_active' => true,
        ]);
        $instance = \App\Modules\Workflows\Models\WorkflowInstance::query()->create([
            'workflow_definition_id' => $definition->id, 'initiated_by' => $owner->id,
            'status' => 'running', 'started_at' => now(),
        ]);
        $this->post($url, ['target_type' => 'correspondence', 'target_id' => $letter->id])->assertSessionHas('success');
        $this->post($url, ['target_type' => 'workflow', 'target_id' => $instance->id])->assertSessionHas('success');
        $this->get(route('archive.show', $record))->assertInertia(fn ($page) => $page
            ->has('references', 3)->where('references.0.title', 'Linked workflow'));
        $this->assertSame(1, $record->versions()->count());

        app(DocumentService::class)->grantAccess($record, $viewer, $owner, 'edit');
        $this->actingAs($viewer)->get(route('archive.show', $record))
            ->assertInertia(fn ($page) => $page->has('references', 0));
        $this->post($url, $payload)->assertNotFound();
    }

    public function test_archive_owner_can_share_and_revoke_a_record_without_granting_global_permissions(): void
    {
        $owner = $this->userWithPermissions(['documents.view', 'documents.create', 'documents.manage_access']);
        $viewer = $this->userWithPermissions(['documents.view']);
        $nonViewer = $this->userWithPermissions([]);
        $this->actingAs($owner)->post(route('archive.store'), [
            'title' => 'Shareable archive', 'document_type' => 'Contract', 'archive_kind' => 'digital',
            'file' => UploadedFile::fake()->create('shared.pdf', 5, 'application/pdf'),
        ])->assertSessionHas('success');

        $record = Document::query()->where('title', 'Shareable archive')->firstOrFail();
        $url = route('archive.access.update', $record);
        $this->get(route('archive.show', $record))->assertInertia(fn ($page) => $page->has('accessManagement.users'));
        $this->put($url, ['user_id' => $nonViewer->id, 'access_level' => 'view'])->assertSessionHasErrors('user_id');
        $this->put($url, ['user_id' => $viewer->id, 'access_level' => 'edit'])->assertSessionHasErrors('access_level');
        $this->put($url, ['user_id' => $viewer->id, 'access_level' => 'view'])->assertSessionHas('success');
        $this->actingAs($viewer)->get(route('archive.show', $record))->assertInertia(fn ($page) => $page
            ->where('accessManagement', null)->where('canUpdate', false));
        $this->get(route('documents.download', $record))->assertOk();
        $this->put($url, ['user_id' => $nonViewer->id, 'access_level' => 'view'])->assertForbidden();

        $this->actingAs($owner)->delete(route('archive.access.destroy', ['document' => $record, 'user' => $viewer]))
            ->assertSessionHas('success');
        $this->actingAs($viewer)->get(route('documents.download', $record))->assertForbidden();
    }

    private function createTask(User $user, string $title): Task
    {
        return Task::query()->create([
            'title' => $title,
            'status' => 'todo',
            'priority' => 'normal',
            'created_by' => $user->id,
            'assignee_id' => $user->id,
            'is_active' => true,
        ]);
    }

    private function userWithPermissions(array $permissionCodes): User
    {
        $role = Role::query()->create([
            'name' => 'Document Test Role '.Str::upper(Str::random(8)),
            'code' => 'document-test-'.Str::lower(Str::random(12)),
            'description' => 'Document foundation test role',
            'is_active' => true,
            'is_system' => false,
        ]);

        foreach ($permissionCodes as $code) {
            $permission = Permission::query()->where('code', $code)->firstOrFail();
            $role->permissions()->attach($permission->id, ['scope' => 'organization']);
        }

        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);

        return $user->fresh();
    }
}
