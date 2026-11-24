<?php

declare(strict_types=1);

namespace App\Modules\Telephony\Http\Controllers;

use App\Core\Departments\Models\Department;
use App\Core\Settings\Services\SystemSettingsService;
use App\Models\User;
use App\Modules\Telephony\Models\Extension;
use App\Modules\Telephony\Services\PbxConfigurationService;
use App\Modules\Telephony\Services\WavePromptValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PbxController
{
    public function index(): Response
    {
        return Inertia::render('Telephony/Extensions', [
            'extensions' => Extension::query()->orderBy('number')->get(),
            'users' => User::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Extension::query()->create($this->validated($request));

        return back()->with('success', 'داخلی ثبت شد. کامپیوتر متصل، تنظیمات را در زمان بیکاری دریافت و اعمال می‌کند.');
    }

    public function update(Request $request, Extension $extension, PbxConfigurationService $configuration): RedirectResponse
    {
        $data = $this->validated($request, $extension);
        DB::transaction(function () use ($data, $extension, $configuration): void {
            $extension = Extension::query()->lockForUpdate()->findOrFail($extension->id);
            if ($data['number'] !== $extension->number || ! $data['is_active']) {
                $configuration->assertNotRouted($extension->number);
            }
            $extension->update($data);
        });

        return back()->with('success', 'داخلی به‌روز شد. تغییرات پس از اعمال بسته روی مرکز تلفن اجرا می‌شوند.');
    }

    private function validated(Request $request, ?Extension $extension = null): array
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'regex:/\A[1-9][0-9]{2,5}\z/', Rule::notIn(['9196', '9197']), Rule::unique('telephony_extensions', 'number')->ignore($extension?->id)],
            'label' => ['required', 'string', 'max:100'],
            'owner_type' => ['required', Rule::in(['user', 'department'])],
            'user_id' => ['nullable', 'required_if:owner_type,user', 'prohibited_unless:owner_type,user', Rule::exists('users', 'id')->where('status', 'active')],
            'department_id' => ['nullable', 'required_if:owner_type,department', 'prohibited_unless:owner_type,department', Rule::exists('departments', 'id')->where('is_active', true)],
            'is_active' => ['required', 'boolean'],
        ]);
        $data['user_id'] = $data['owner_type'] === 'user' ? $data['user_id'] : null;
        $data['department_id'] = $data['owner_type'] === 'department' ? $data['department_id'] : null;

        return $data;
    }

    public function ivr(PbxConfigurationService $configuration): Response
    {
        $menu = $configuration->menu();
        unset($menu['audio_path']);

        return Inertia::render('Telephony/Ivr', [
            'menu' => $menu,
            'extensions' => Extension::query()->where('is_active', true)->orderBy('number')->get(['number', 'label']),
        ]);
    }

    public function audio(PbxConfigurationService $configuration): BinaryFileResponse
    {
        $path = $configuration->menu()['audio_path'];
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => 'audio/wav',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function saveIvr(Request $request, SystemSettingsService $settings, PbxConfigurationService $configuration): RedirectResponse
    {
        $request->merge(['choices' => $request->input('choices', [])]);
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'timeout_seconds' => ['required', 'integer', 'between:3,30'],
            'retries' => ['required', 'integer', 'between:1,3'],
            'operator_number' => ['nullable', 'required_if:enabled,1', Rule::exists('telephony_extensions', 'number')->where('is_active', true)],
            'choices' => ['present', 'array', 'max:10'],
            'choices.*' => ['required', 'array:digit,number'],
            'choices.*.digit' => ['required', 'string', 'regex:/\A[0-9]\z/', 'distinct'],
            'choices.*.number' => ['required', 'string', Rule::exists('telephony_extensions', 'number')->where('is_active', true)],
            'audio' => ['nullable', 'file', 'max:20480'],
        ]);
        $existing = $configuration->menu();
        $audio = $request->file('audio');
        if ($audio) {
            app(WavePromptValidator::class)->validate($audio);
        }
        if ($data['enabled'] && ! $audio && ! $existing['audio_path']) {
            throw ValidationException::withMessages(['audio' => 'برای فعال کردن راهنما، فایل صوتی WAV را بارگذاری کنید.']);
        }
        $path = $audio ? $audio->store('telephony/prompts', 'local') : $existing['audio_path'];
        if ($audio && ! $path) {
            throw ValidationException::withMessages(['audio' => 'ذخیره فایل صوتی ممکن نشد. دوباره تلاش کنید.']);
        }
        try {
            $settings->set(['telephony.ivr' => json_encode([
                'enabled' => (bool) $data['enabled'], 'timeout_seconds' => $data['timeout_seconds'],
                'retries' => $data['retries'], 'operator_number' => $data['operator_number'] ?? null,
                'choices' => $data['choices'], 'audio_path' => $path,
                'audio_name' => $audio ? 'فایل راهنمای بارگذاری‌شده' : $existing['audio_name'],
            ], JSON_THROW_ON_ERROR)]);
        } catch (\Throwable $exception) {
            if ($audio) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return back()->with('success', 'تنظیمات راهنمای صوتی ذخیره شد. کامپیوتر متصل، تغییرات را پس از پایان تماس اعمال می‌کند.');
    }

    public function download(PbxConfigurationService $configuration): BinaryFileResponse
    {
        abort_unless(class_exists(\ZipArchive::class), 503);
        $manifest = $configuration->manifest();
        $menu = $configuration->menu();
        $directory = storage_path('app/private');
        File::ensureDirectoryExists($directory);
        $file = tempnam($directory, 'pbx-settings-');
        $zip = new \ZipArchive;
        try {
            if ($zip->open($file, \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Cannot create configuration archive');
            }
            $zip->addFromString('office-telephony.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            if ($manifest['ivr']['audio_file']) {
                abort_unless(Storage::disk('local')->exists($menu['audio_path']), 409, 'فایل راهنما در دسترس نیست؛ دوباره بارگذاری کنید.');
                $zip->addFile(Storage::disk('local')->path($menu['audio_path']), 'greeting.wav');
            }
            foreach (['Apply-OfficeConfig.ps1', 'Apply-OfficeConfig.cmd', 'PbxLab.psm1', 'freeswitch.template.xml', 'Normalize-Prompt.cs', 'Start-PbxLab.ps1', 'Start-PbxLab.cmd', 'Stop-PbxLab.ps1', 'Stop-PbxLab.cmd', 'Configure-PbxLab.ps1', 'Collect-PbxLabDiagnostics.ps1', 'Collect-PbxLabDiagnostics.cmd', 'README.fa.md'] as $name) {
                if (! $zip->addFile(base_path('tools/WindowsTelephony/PbxLab/'.$name), $name)) {
                    throw new \RuntimeException('Configuration helper file unavailable');
                }
            }
            $zip->close();
        } catch (\Throwable $exception) {
            $zip->close();
            @unlink($file);
            throw $exception;
        }

        return response()->download($file, 'OfficeAutomation-PhoneSettings.zip', ['Cache-Control' => 'no-store'])->deleteFileAfterSend(true);
    }
}
