<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Providers;

use App\Modules\Notifications\Listeners\TaskNotificationListener;
use App\Modules\Tasks\Events\TaskApproved;
use App\Modules\Tasks\Events\TaskAssigned;
use App\Modules\Tasks\Events\TaskReturned;
use App\Modules\Tasks\Events\TaskSubmitted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class NotificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $listener = $this->app->make(TaskNotificationListener::class);

        Event::listen(TaskAssigned::class, [$listener, 'assigned']);
        Event::listen(TaskSubmitted::class, [$listener, 'submitted']);
        Event::listen(TaskReturned::class, [$listener, 'returned']);
        Event::listen(TaskApproved::class, [$listener, 'approved']);
    }
}