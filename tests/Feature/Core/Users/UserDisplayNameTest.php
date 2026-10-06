<?php

namespace Tests\Feature\Core\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Core\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class UserDisplayNameTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithPermissions;

    public function test_user_display_name_is_derived_from_first_and_last_name(): void
    {
        $admin = User::factory()->create();

        $this->grantPermissions($admin, ['users.create', 'users.edit']);

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'username' => 'derived-name-user',
            'email' => 'derived-name@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'status' => 'active',
            'role_ids' => [],
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();

        $user = User::query()->where('username', 'derived-name-user')->firstOrFail();

        $this->assertSame('علی رضایی', $user->name);

        $this->actingAs($admin)->put(route('users.update', $user), [
            'username' => 'derived-name-user',
            'email' => 'derived-name@example.com',
            'first_name' => 'مریم',
            'last_name' => 'احمدی',
            'status' => 'active',
            'role_ids' => [],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('مریم احمدی', $user->refresh()->name);
    }
}
