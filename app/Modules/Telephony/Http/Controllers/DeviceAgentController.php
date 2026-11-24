<?php

declare(strict_types=1);

namespace App\Modules\Telephony\Http\Controllers;

use App\Modules\Telephony\Models\Device;
use App\Modules\Telephony\Services\PbxConfigurationArchive;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DeviceAgentController
{
    public function pair(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pairing_code' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'computer_name' => ['required', 'string', 'max:120'],
        ]);
        $token = bin2hex(random_bytes(32));
        $device = DB::transaction(function () use ($data, $token): Device {
            $device = Device::query()->where('status', 'awaiting_pairing')
                ->where('pairing_hash', hash('sha256', $data['pairing_code']))
                ->where('pairing_expires_at', '>', now())->lockForUpdate()->first();
            abort_unless($device, 401);
            $device->update([
                'status' => 'linked', 'computer_name' => $data['computer_name'],
                'pairing_hash' => null, 'pairing_expires_at' => null,
                'token_hash' => hash('sha256', $token), 'paired_at' => now(), 'last_seen_at' => now(),
            ]);

            return $device;
        });

        return response()->json(['device_id' => $device->id, 'name' => $device->name, 'token' => $token])
            ->header('Cache-Control', 'no-store');
    }

    public function heartbeat(Request $request, PbxConfigurationArchive $archive): JsonResponse
    {
        $data = $request->validate([
            'runtime_state' => ['required', 'in:ready,busy,stopped,error'],
            'applied_revision' => ['nullable', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'error_code' => ['nullable', 'string', 'max:80', 'regex:/\A[a-z0-9_]+\z/'],
        ]);
        $device = $request->attributes->get('telephony_device');
        $device->update([
            'last_seen_at' => now(), 'runtime_state' => $data['runtime_state'],
            'applied_revision' => $data['applied_revision'] ?? $device->applied_revision,
            'error_code' => $data['error_code'] ?? null,
        ]);
        try {
            $revision = $archive->snapshot()['revision'];
        } catch (ValidationException) {
            $revision = null;
        }

        return response()->json(['configuration_revision' => $revision, 'configuration_ready' => $revision !== null])
            ->header('Cache-Control', 'no-store');
    }

    public function configuration(PbxConfigurationArchive $archive): BinaryFileResponse
    {
        $snapshot = $archive->snapshot();

        return response()->download($archive->build($snapshot), 'office-telephony.zip', [
            'Cache-Control' => 'no-store', 'X-Office-Configuration-Revision' => $snapshot['revision'],
        ])->deleteFileAfterSend(true);
    }
}
