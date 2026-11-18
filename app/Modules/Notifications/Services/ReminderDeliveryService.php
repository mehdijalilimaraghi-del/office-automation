<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Models\User;
use App\Modules\Notifications\Notifications\ReminderNotification;
use Illuminate\Support\Facades\DB;

final class ReminderDeliveryService
{
    public function sendOnce(User $recipient, string $key, array $payload): bool
    {
        return DB::transaction(function () use ($recipient, $key, $payload): bool {
            $user = User::query()->whereKey($recipient->id)->lockForUpdate()->firstOrFail();
            if (! $user->isActive()) {
                return false;
            }
            $key = hash('sha256', $key);
            if (DB::table('reminder_deliveries')->where('user_id', $user->id)->where('delivery_key', $key)->exists()) {
                return false;
            }
            $user->notify(new ReminderNotification($payload));
            DB::table('reminder_deliveries')->insert(['user_id' => $user->id, 'delivery_key' => $key, 'sent_at' => now()]);

            return true;
        });
    }
}
