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
