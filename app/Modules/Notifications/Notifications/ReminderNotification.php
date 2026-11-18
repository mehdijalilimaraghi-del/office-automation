<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use Illuminate\Notifications\Notification;

final class ReminderNotification extends Notification
{
    public function __construct(private readonly array $payload) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }
}
