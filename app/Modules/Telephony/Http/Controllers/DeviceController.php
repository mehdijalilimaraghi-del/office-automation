<?php

declare(strict_types=1);

namespace App\Modules\Telephony\Http\Controllers;

use App\Modules\Telephony\Models\Device;
use App\Modules\Telephony\Services\PbxConfigurationArchive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController
{
    public function index(Request $request, PbxConfigurationArchive $archive): Response
    {
        $revision = null;
        try {
            $revision = $archive->snapshot()['revision'];
        } catch (ValidationException) {
            // The operator can pair a computer before defining the first extension.
        }

        return Inertia::render('Telephony/Devices', [
            'devices' => Device::query()->latest('id')->get()->map(fn (Device $device): array => [
                'id' => $device->id, 'name' => $device->name, 'computer_name' => $device->computer_name,
                'status' => $device->status, 'runtime_state' => $device->runtime_state,
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                'online' => $device->status === 'linked' && $device->last_seen_at?->gt(now()->subSeconds(90)),
                'applied_revision' => $device->applied_revision,
                'configuration_pending' => $revision !== null && $device->applied_revision !== $revision,
                'error_code' => $device->error_code,
            ]),
            'pairing' => $request->session()->pull('telephony_pairing'),
            'configurationReady' => $revision !== null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $code = bin2hex(random_bytes(32));
        $device = Device::query()->create([
            'name' => $data['name'], 'created_by' => $request->user()->id,
            'pairing_hash' => hash('sha256', $code), 'pairing_expires_at' => now()->addMinutes(10),
        ]);

        return to_route('telephony.devices.index')->with('telephony_pairing', [
            'device_id' => $device->id, 'name' => $device->name, 'code' => $code,
            'expires_at' => $device->pairing_expires_at->toIso8601String(),
        ])->with('success', 'کد اتصال آماده شد؛ آن را در برنامه کامپیوتر مودم وارد کنید.');
    }

    public function revoke(Device $device): RedirectResponse
    {
        $device->update(['status' => 'revoked', 'token_hash' => null, 'pairing_hash' => null, 'pairing_expires_at' => null]);

        return back()->with('success', 'اتصال این کامپیوتر لغو شد. تماس جاری از راه دور قطع نمی‌شود.');
    }

    public function regenerate(Device $device): RedirectResponse
    {
        $code = bin2hex(random_bytes(32));
        DB::transaction(function () use ($device, $code): void {
            $locked = Device::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'linked') {
                throw ValidationException::withMessages(['pairing' => 'ابتدا اتصال فعلی این کامپیوتر را لغو کنید.']);
            }
            $locked->update(['status' => 'awaiting_pairing', 'pairing_hash' => hash('sha256', $code), 'pairing_expires_at' => now()->addMinutes(10), 'token_hash' => null]);
        });
        $device->refresh();

        return to_route('telephony.devices.index')->with('telephony_pairing', [
            'device_id' => $device->id, 'name' => $device->name, 'code' => $code,
            'expires_at' => $device->pairing_expires_at->toIso8601String(),
        ])->with('success', 'کد اتصال جدید آماده شد؛ کد قبلی دیگر معتبر نیست.');
    }
}
