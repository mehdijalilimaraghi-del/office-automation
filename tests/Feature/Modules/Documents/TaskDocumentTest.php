<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Documents;

use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Modules\Tasks\Models\Task;
use Database\Seeders\CorePermissionSeeder;
use Database\Seeders\CoreRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_assignee_with_task_access_can_attach_and_download_without_document_permissions(): void
    {
        Storage::fake('local');

        $user = $this->userWithPermissions(['tasks.view', 'tasks.update']);
        $task = Task::query()->create([
            'title' => 'Assigned task',
            'status' => 'todo',
            'priority' => 'normal',
            'created_by' => $user->id,
            'assignee_id' => $user->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('tasks.show', $task))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Show')
                ->where('abilities.documents_create', true));

        $this->actingAs($user)
            ->post(route('tasks.documents.store', $task), [
                'title' => 'فایل مسئول تسک',
                'file' => UploadedFile::fake()->create('assigned-guide.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('tasks.show', $task));

        $document = Document::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('documents.download', $document))
            ->assertOk()
            ->assertDownload('assigned-guide.pdf');
    }

    public function test_user_without_task_access_cannot_attach_document_even_without_document_permissions(): void
    {
        Storage::fake('local');

        $creator = User::factory()->create(['status' => 'active']);
        $unauthorized = User::factory()->create(['status' => 'active']);

        $task = Task::query()->create([
            'title' => 'Protected task',
            'status' => 'todo',
            'priority' => 'normal',
            'created_by' => $creator->id,
            'assignee_id' => $creator->id,
            'is_active' => true,
        ]);

        $this->actingAs($unauthorized)
            ->post(route('tasks.documents.store', $task), [
                'title' => 'نباید بارگذاری شود',
                'file' => UploadedFile::fake()->create('guide.pdf', 120, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('documents', 0);
    }

    private function userWithPermissions(array $permissionCodes): User
    {
        $this->seed(CorePermissionSeeder::class);

        $role = Role::query()->create([
            'name' => 'Task document test role',
            'code' => 'task-document-'.Str::lower(Str::random(12)),
            'description' => null,
            'is_active' => true,
            'is_system' => false,
        ]);

        foreach ($permissionCodes as $code) {
            $permission = Permission::query()->where('code', $code)->firstOrFail();
            $role->permissions()->attach($permission->id, ['scope' => 'self']);
        }

        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);

        return $user->fresh();
    }
}
