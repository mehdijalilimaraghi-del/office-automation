<?php

use App\Core\CoreServiceProvider;
use App\Providers\AppServiceProvider;

return [
    App\Providers\AppServiceProvider::class,
    App\Core\CoreServiceProvider::class,
    App\Modules\Notifications\Providers\NotificationServiceProvider::class,
];