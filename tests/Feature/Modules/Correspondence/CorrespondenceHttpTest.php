<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Correspondence;

use App\Core\Permissions\Models\Permission;
use App\Core\Permissions\Models\PermissionScope;
use App\Core\Roles\Models\Role;
use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    public function test_user_with_create_permission_can_create_a_draft(): void
    {
        $user = $this->userWithPermission('correspondence.create');

        $this->actingAs($user)->post(route('correspondence.store'), [
            'type' => 'incoming',
            'subject' => '  نامه وارده  ',
            'body' => 'متن آزمایشی',
            'sender_name' => 'فرستنده',
        ])->assertRedirect(route('correspondence.index'));

        $this->assertDatabaseHas('correspondences', [
            'type' => 'incoming',
            'subject' => 'نامه وارده',
            'status' => 'draft',
            'registration_number' => null,
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
        $permission = Permission::query()->where('code', $permissionCode)->firstOrFail();
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
