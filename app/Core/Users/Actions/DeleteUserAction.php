<?php

namespace App\Core\Users\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class DeleteUserAction
{
    public function execute(User $user): void
    {
        if ($user->id === auth()->id()) {
            throw ValidationException::withMessages([
                'user' => 'کاربر جاری قابل حذف نیست.',
            ]);
        }

        $user->delete();
    }
}