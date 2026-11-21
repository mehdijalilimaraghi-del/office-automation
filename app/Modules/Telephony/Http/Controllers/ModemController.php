<?php

declare(strict_types=1);

namespace App\Modules\Telephony\Http\Controllers;

use App\Core\Settings\Services\SystemSettingsService;
use App\Modules\Telephony\Models\Discovery;
use App\Modules\Telephony\Models\Modem;
use App\Modules\Telephony\Services\ModemDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ModemController
{
    public function index(SystemSettingsService $settings): Response
    {
        return Inertia::render('Telephony/Index', [
            'modems' => Modem::query()->orderByDesc('last_seen_at')->get()->map(fn ($modem) => [
                ...$modem->only(['id', 'computer_name', 'name', 'port', 'driver_status', 'voice_advertised', 'probe_status']),
                'last_seen_at' => $modem->last_seen_at->toIso8601String(),
                'fresh' => $modem->last_seen_at->gte(now()->subMinutes(15)),
            ]),
            'selectedId' => $settings->get(['telephony.modem.selected_id' => null])['telephony.modem.selected_id'],
        ]);
    }

    public function helper(Request $request): BinaryFileResponse
    {
        abort_unless(class_exists(\ZipArchive::class), 503, 'ساخت بسته واسط در این سرور در دسترس نیست.');
        $directory = storage_path('app/private');
        File::ensureDirectoryExists($directory);
        $file = tempnam($directory, 'modem-helper-');
        $zip = new \ZipArchive;
        if ($zip->open($file, \ZipArchive::OVERWRITE) !== true) {
            @unlink($file);
            abort(503);
        }
        try {
            foreach (['ModemHelper.ps1', 'Install.ps1', 'Run-Offline.cmd', 'README.md'] as $name) {
                if (! $zip->addFile(base_path('tools/WindowsTelephony/'.$name), $name)) {
                    throw new \RuntimeException('Helper file unavailable');
                }
            }
            $zip->addFromString('settings.json', json_encode(['server_url' => $request->getSchemeAndHttpHost()], JSON_THROW_ON_ERROR));
            $zip->close();
        } catch (\Throwable $exception) {
            $zip->close();
            @unlink($file);
            throw $exception;
        }

        return response()->download($file, 'OfficeAutomation-ModemHelper.zip')->deleteFileAfterSend(true);
    }

    public function store(Request $request): RedirectResponse
    {
        $token = bin2hex(random_bytes(32));
        $discovery = Discovery::query()->create([
            'user_id' => $request->user()->id, 'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(config('telephony.discovery_minutes', 10)),
        ]);
        $request->session()->put('telephony.discovery.'.$discovery->id, $token);

        return redirect()->route('telephony.discoveries.show', $discovery);
    }

    private function authorizeOwner(Request $request, Discovery $discovery): void
    {
        abort_unless((int) $discovery->user_id === (int) $request->user()->id, 403);
    }

    public function show(Request $request, Discovery $discovery): Response
    {
        $this->authorizeOwner($request, $discovery);
        $token = $request->session()->get('telephony.discovery.'.$discovery->id);

        return Inertia::render('Telephony/Discovery', [
            'discovery' => $discovery->only(['id', 'expires_at', 'completed_at', 'device_count']),
            'protocolUrl' => $token && $discovery->expires_at->isFuture() && $discovery->completed_at === null
                ? 'office-telephony://discover?upload='.rawurlencode(route('telephony.agent.report', $token)) : null,
        ]);
    }

    public function status(Request $request, Discovery $discovery): JsonResponse
    {
        $this->authorizeOwner($request, $discovery);

        return response()->json(['complete' => $discovery->completed_at !== null, 'expired' => $discovery->expires_at->isPast()]);
    }

    public function report(Request $request, string $token, ModemDiscoveryService $discoveryService): JsonResponse
    {
        abort_if(strlen($request->getContent()) > 131072, 413);
        $data = $request->validate([
            'computer_key' => ['required', 'uuid'], 'computer_name' => ['required', 'string', 'max:100'],
            'devices' => ['present', 'array', 'max:32'],
            'devices.*.device_key' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/', 'distinct'],
            'devices.*.name' => ['required', 'string', 'max:255'],
            'devices.*.port' => ['nullable', 'regex:/^COM[1-9][0-9]{0,4}$/'],
            'devices.*.driver_status' => ['required', Rule::in(['OK', 'unavailable', 'unknown'])],
            'devices.*.probe_status' => ['required', Rule::in(['not_tested', 'queried', 'failed'])],
            'devices.*.evidence' => ['sometimes', 'array:identity,classes,voice_formats'],
            'devices.*.evidence.*' => ['nullable', 'string', 'max:4096'],
        ]);
        $discoveryService->accept($token, $data);

        return response()->json(['received' => true]);
    }

    public function select(Request $request, SystemSettingsService $settings): RedirectResponse
    {
        $data = $request->validate(['modem_id' => ['required', 'integer', 'exists:telephony_modems,id']]);
        $modem = Modem::query()->findOrFail($data['modem_id']);
        if (! $modem->voice_advertised || $modem->driver_status !== 'OK' || ! $modem->port || $modem->last_seen_at->lt(now()->subMinutes(15))) {
            throw ValidationException::withMessages(['modem_id' => 'برای انتخاب، مودم باید گزارش جدید و قابلیت صوت اعلام‌شده داشته باشد. بررسی مودم را دوباره اجرا کنید.']);
        }
        $settings->set(['telephony.modem.selected_id' => (string) $modem->id]);

        return back()->with('success', 'مودم برای آزمون مکالمه انتخاب شد. این انتخاب هنوز خط را فعال نمی‌کند.');
    }
}
