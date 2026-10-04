<?php

namespace Tests\Feature\Core\Users;

use App\Core\Users\Models\UserInvitation;
use App\Core\Users\Notifications\UserInvitationNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Core\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class UserInvitationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithPermissions;

    public function test_inactive_user_creation_sends_invitation_and_keeps_account_inactive(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        $this->grantPermissions($admin, ['users.create']);

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'دعوت شده',
            'email' => 'invited@example.com',
            'status' => 'inactive',
            'role_ids' => [],
            'department_ids' => [],
            'primary_department_id' => null,
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        $user = User::where('email', 'invited@example.com')->firstOrFail();

        $this->assertSame('inactive', $user->status);
        $this->assertDatabaseHas('user_invitations', [
            'user_id' => $user->id,
            'accepted_at' => null,
        ]);

        Notification::assertSentTo($user, UserInvitationNotification::class);
    }

    public function test_invitation_can_activate_user_with_new_password(): void
    {
        $user = User::factory()->inactive()->create([
            'email' => 'invited@example.com',
        ]);

        $token = 'test-invitation-token';
        $invitation = UserInvitation::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->post(route('users.invitation.store'), [
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect(route('login'));

        $user->refresh();
        $invitation->refresh();

        $this->assertSame('active', $user->status);
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertNotNull($invitation->accepted_at);
    }

    public function test_expired_invitation_is_rejected(): void
    {
        $user = User::factory()->inactive()->create();

        $token = 'expired-token';

        UserInvitation::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->subMinute(),
        ]);

        $this->post(route('users.invitation.store'), [
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasErrors('token');

        $this->assertSame('inactive', $user->fresh()->status);
    }
}
