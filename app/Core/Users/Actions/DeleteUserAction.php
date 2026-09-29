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

        if ($user->managedDepartments()->exists()) {
            throw ValidationException::withMessages([
                'user' => 'این کاربر مدیر یک یا چند دپارتمان است. ابتدا مدیریت دپارتمان‌ها را حذف یا تغییر دهید.',
            ]);
        }

        $user->delete();
    }
}