<?php

namespace App\Core\Users\Notifications;

use App\Core\Users\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly UserInvitation $invitation,
        private readonly string $token,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('دعوت به سامانه Office Automation')
            ->greeting('سلام '.$notifiable->name)
            ->line('حساب کاربری شما در سامانه Office Automation ایجاد شده است.')
            ->line('برای تعیین رمز عبور و فعال‌سازی حساب، از لینک زیر استفاده کنید.')
            ->action('فعال‌سازی حساب', route('users.invitation.accept', ['token' => $this->token]))
            ->line('این لینک به مدت 48 ساعت معتبر است.');
    }
}
