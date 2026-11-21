<?php

declare(strict_types=1);

namespace App\Modules\Telephony\Services;

use App\Models\User;
use App\Modules\Telephony\Models\Discovery;
use App\Modules\Telephony\Models\Modem;
use Illuminate\Support\Facades\DB;

final class ModemDiscoveryService
{
    public function accept(string $token, array $data): void
    {
        DB::transaction(function () use ($data, $token): void {
            $discovery = Discovery::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            abort_if($discovery === null, 404);
            abort_if($discovery->expires_at->isPast(), 410);
            abort_if($discovery->completed_at !== null, 409);
            $owner = User::query()->find($discovery->user_id);
            abort_unless($owner?->isActive() && $owner->hasPermission('settings.manage'), 403);
            Modem::query()->where('computer_key', $data['computer_key'])
                ->whereNotIn('device_key', array_column($data['devices'], 'device_key'))
                ->update(['driver_status' => 'unavailable', 'voice_advertised' => false, 'last_seen_at' => now()]);
            foreach ($data['devices'] as $device) {
                $classes = $device['evidence']['classes'] ?? '';
                $advertised = $device['probe_status'] === 'queried'
                    && preg_match('/\+FCLASS:\s*([^\r\n]+)/i', $classes, $match)
                    && preg_match('/(?:^|[^0-9])8(?:[^0-9]|$)/', $match[1]);
                Modem::query()->updateOrCreate(['device_key' => $device['device_key']], [
                    'computer_key' => $data['computer_key'], 'computer_name' => $data['computer_name'],
                    'name' => $device['name'], 'port' => $device['port'] ?? null,
                    'driver_status' => $device['driver_status'], 'probe_status' => $device['probe_status'],
                    'evidence' => $device['evidence'] ?? [], 'voice_advertised' => (bool) $advertised,
                    'last_seen_at' => now(),
                ]);
            }
            $discovery->update(['completed_at' => now(), 'device_count' => count($data['devices'])]);
        });
    }
}
