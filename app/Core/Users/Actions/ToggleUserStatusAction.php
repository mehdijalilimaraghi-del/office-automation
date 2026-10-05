<?php

namespace App\Core\Users\Actions;

use App\Models\User;
use App\Core\Audit\Contracts\AuditServiceInterface;
use Illuminate\Validation\ValidationException;

class ToggleUserStatusAction
{
    public function __construct(private readonly AuditServiceInterface $audit)
    {
    }

    public function execute(User $user): User
    {
        if ($user->hasRole('SYSTEM_ADMIN')) {
            throw ValidationException::withMessages(['user' => 'وضعیت مدیر سیستم قابل تغییر نیست.']);
        }
        if ($user->id === auth()->id()) {
            throw ValidationException::withMessages([
                'user' => 'وضعیت کاربر جاری قابل تغییر نیست.',
            ]);
        }

        if ($user->isInactive()) {
            throw ValidationException::withMessages([
                'user' => 'فعال‌سازی کاربر باید از طریق دعوت‌نامه انجام شود.',
            ]);
        }

        if ($user->managedProjects()->exists()) {
            throw ValidationException::withMessages([
                'user' => 'این کاربر مدیر یک یا چند پروژه است. ابتدا مدیریت پروژه‌ها را حذف یا تغییر دهید.',
            ]);
        }

        $oldStatus = $user->status;

        $user->update([
            'status' => $user->isActive()
                ? 'inactive'
                : 'active',
        ]);

        $user->refresh();

        $this->audit->record('user.status_changed', $user, ['status' => $oldStatus], ['status' => $user->status]);

        return $user;
    }
}