<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Documents;

use App\Core\Roles\Models\Role;
use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Modules\Tasks\Models\Task;
use Database\Seeders\CorePermissionSeeder;
use Database\Seeders\CoreRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function systemAdmin(): User
    {
        $this->seed([
            CorePermissionSeeder::class,
            CoreRoleSeeder::class,
        ]);

        $user = User::factory()->create(['status' => 'active']);
        $role = Role::query()->where('code', 'SYSTEM_ADMIN')->firstOrFail();

        $user->roles()->attach($role);

        return $user;
    }

    public function test_authorized_user_can_attach_document_to_task_and_download_it(): void
    {
        Storage::fake('local');

        $user = $this->systemAdmin();

        $task = Task::query()->create([
            'title' => 'Task with attachment',
            'description' => 'Document attachment test.',
            'status' => 'todo',
            'priority' => 'normal',
            'created_by' => $user->id,
            'assignee_id' => $user->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('tasks.documents.store', $task), [
                'title' => 'راهنمای فنی',
                'document_type' => 'گزارش',
                'file' => UploadedFile::fake()->create('guide.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('tasks.show', $task));

        $document = Document::query()->firstOrFail();

        $this->assertDatabaseHas('document_links', [
            'document_id' => $document->id,
            'linkable_type' => $task->getMorphClass(),
            'linkable_id' => $task->id,
            'purpose' => 'attachment',
            'created_by' => $user->id,
        ]);

        $version = $document->latestVersion()->firstOrFail();

        Storage::disk('local')->assertExists($version->path);

        $this->actingAs($user)
            ->get(route('documents.download', $document))
            ->assertOk()
            ->assertDownload('guide.pdf');
    }

    public function test_task_attachment_requires_document_create_permission(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['status' => 'active']);

        $task = Task::query()->create([
            'title' => 'Protected task',
            'status' => 'todo',
            'priority' => 'normal',
            'created_by' => $user->id,
            'assignee_id' => $user->id,
            'is_active' => true,
        ]);

        $user->roles()->firstOrFail()->permissions()->detach(
            \App\Core\Permissions\Models\Permission::query()
                ->where('code', 'documents.create')
                ->value('id'),
        );

        $this->actingAs($user)
            ->post(route('tasks.documents.store', $task), [
                'title' => 'بدون مجوز',
                'file' => UploadedFile::fake()->create('guide.pdf', 120, 'application/pdf'),
            ])
            ->assertForbidden();
    }
}
