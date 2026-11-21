<?php

declare(strict_types=1);

namespace App\Modules\Telephony\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class TelephonyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('telephony.manage', fn (User $user): bool => $user->isActive() && $user->hasPermission('settings.manage'));
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
    }
}
