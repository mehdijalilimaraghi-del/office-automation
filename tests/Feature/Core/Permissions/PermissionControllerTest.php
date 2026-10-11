<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Permissions;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Core\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class PermissionControllerTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithPermissions;

    public function test_authenticated_user_with_view_permission_can_view_read_only_catalog(): void
    {
        $user = User::factory()->create();

        $this->grantPermissions($user, ['permissions.view']);

        $this->actingAs($user)
            ->get(route('permissions.index'))
            ->assertOk();
    }

    public function test_permission_catalog_does_not_register_mutation_routes(): void
    {
        $this->assertFalse(Route::has('permissions.store'));
        $this->assertFalse(Route::has('permissions.update'));
        $this->assertFalse(Route::has('permissions.toggle-status'));
        $this->assertFalse(Route::has('permissions.destroy'));
    }

    public function test_permission_catalog_rejects_http_mutations(): void
    {
        $user = User::factory()->create();
        $this->grantPermissions($user, ['permissions.view']);

        $this->actingAs($user)
            ->post('/permissions', [
                'name' => 'مجوز آزمایشی',
                'code' => 'reports.view',
            ])
            ->assertStatus(405);

        $this->actingAs($user)
            ->put('/permissions/1', [
                'name' => 'مجوز ویرایش‌شده',
                'code' => 'reports.view',
            ])
            ->assertNotFound();

        $this->actingAs($user)
            ->patch('/permissions/1/toggle-status')
            ->assertNotFound();

        $this->actingAs($user)
            ->delete('/permissions/1')
            ->assertNotFound();
    }
}
