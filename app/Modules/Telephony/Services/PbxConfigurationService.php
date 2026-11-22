<?php

declare(strict_types=1);

namespace App\Modules\Telephony\Services;

use App\Core\Settings\Services\SystemSettingsService;
use App\Modules\Telephony\Models\Extension;
use Illuminate\Validation\ValidationException;

final class PbxConfigurationService
{
    public function menu(): array
    {
        $value = app(SystemSettingsService::class)->get(['telephony.ivr' => null])['telephony.ivr'];

        return $value ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : [
            'enabled' => false, 'timeout_seconds' => 10, 'retries' => 2,
            'operator_number' => null, 'choices' => [], 'audio_path' => null, 'audio_name' => null,
        ];
    }

    public function assertNotRouted(string $number): void
    {
        $menu = $this->menu();
        if ($menu['enabled'] && ($menu['operator_number'] === $number
            || in_array($number, array_column($menu['choices'], 'number'), true))) {
            throw ValidationException::withMessages(['is_active' => 'ابتدا مقصد این داخلی را در راهنمای صوتی تغییر دهید.']);
        }
    }

    public function manifest(): array
    {
        $extensions = Extension::query()->where('is_active', true)->orderBy('number')->get();
        $menu = $this->menu();
        if ($extensions->isEmpty()) {
            throw ValidationException::withMessages(['configuration' => 'ابتدا حداقل یک داخلی فعال تعریف کنید.']);
        }
        $numbers = $extensions->pluck('number')->all();
        if ($menu['enabled']) {
            foreach (array_filter([$menu['operator_number'], ...array_column($menu['choices'], 'number')]) as $number) {
                if (! in_array($number, $numbers, true)) {
                    throw ValidationException::withMessages(['configuration' => 'یکی از مقصدهای راهنمای صوتی غیرفعال است. تنظیمات را اصلاح کنید.']);
                }
            }
        }

        return [
            'schema_version' => 1, 'type' => 'office_telephony_configuration',
            'extensions' => $extensions->map(fn (Extension $extension) => $extension->only(['number', 'label', 'owner_type', 'user_id', 'department_id']))->all(),
            'ivr' => [
                'enabled' => $menu['enabled'], 'timeout_seconds' => $menu['timeout_seconds'],
                'retries' => $menu['retries'], 'operator_number' => $menu['operator_number'],
                'choices' => $menu['choices'], 'audio_file' => $menu['audio_path'] ? 'greeting.wav' : null,
            ],
        ];
    }
}
