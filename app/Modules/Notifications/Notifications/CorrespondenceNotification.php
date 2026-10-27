<?php

declare(strict_types=1);

namespace App\\Modules\\Notifications\\Notifications;

use App\\Modules\\Correspondence\\Models\\Correspondence;
use Illuminate\\Bus\\Queueable;
use Illuminate\\Notifications\\Notification;

final class CorrespondenceNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $notificationType,
        private readonly string $title,
        private readonly Correspondence $correspondence,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => $this->notificationType,
            'title' => $this->title,
            // Never put the subject, body, or workflow note in a notification payload.
            'message' => 'برای مشاهده جزئیات، وارد سامانه شوید.',
            'correspondence_id' => (int) $this->correspondence->id,
            'url' => route('correspondence.show', $this->correspondence),
        ];
    }
}
