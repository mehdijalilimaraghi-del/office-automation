<?php

namespace App\Core\Settings\Services;

use App\Core\Settings\Models\SystemSetting;

class SystemSettingsService
{
    /**
     * @param array<string, string|null> $keys
     * @return array<string, string|null>
     */
    public function get(array $keys): array
    {
        $stored = SystemSetting::query()
            ->whereIn('key', array_keys($keys))
            ->pluck('value', 'key');

        $result = [];

        foreach ($keys as $key => $default) {
            $result[$key] = $stored->get($key, $default);
        }

        return $result;
    }

    /**
     * @param array<string, string|null> $values
     */
    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
