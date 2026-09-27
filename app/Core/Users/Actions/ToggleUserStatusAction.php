<?php

namespace App\Core\Users\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class ToggleUserStatusAction
{
    public function execute(User $user): User
    {
        if ($user->id === auth()->id()) {
            throw ValidationException::withMessages([
                'user' => 'وضعیت کاربر جاری قابل تغییر نیست.',
            ]);
        }

        $user->update([
            'status' => $user->isActive()
                ? 'inactive'
                : 'active',
        ]);

        return $user->refresh();
    }
}