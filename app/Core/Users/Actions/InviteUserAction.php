<?php

namespace App\Core\Users\Actions;

use App\Core\Users\Models\UserInvitation;
use App\Core\Users\Notifications\UserInvitationNotification;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteUserAction
{
    public function execute(User $user): void
    {
        if ($user->hasRole('SYSTEM_ADMIN')) {
            throw ValidationException::withMessages([
                'user' => 'مدیر سیستم نیازی به دعوت‌نامه ندارد.',
            ]);
        }

        if ($user->isActive()) {
            throw ValidationException::withMessages([
                'user' => 'این کاربر قبلاً فعال شده است.',
            ]);
        }

        $token = Str::random(64);

        $invitation = UserInvitation::updateOrCreate(
            ['user_id' => $user->id],
            [
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addHours(48),
                'accepted_at' => null,
            ]
        );

        $user->notify(new UserInvitationNotification($invitation, $token));
    }
}
