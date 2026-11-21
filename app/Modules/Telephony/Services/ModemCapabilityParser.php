<?php

declare(strict_types=1);

namespace App\Modules\Telephony\Services;

final class ModemCapabilityParser
{
    public function advertisesVoice(string $response): bool
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/[\r\n]+/', $response) ?: [])));
        if (! in_array('OK', $lines, true) || in_array('ERROR', $lines, true)) {
            return false;
        }
        foreach ($lines as $line) {
            $value = trim(preg_replace('/^\+FCLASS:\s*/i', '', $line));
            if (str_starts_with($value, '(') && str_ends_with($value, ')')) {
                $value = trim(substr($value, 1, -1));
            }
            if (! preg_match('/^\d+(?:\.\d+)?(?:\s*,\s*\d+(?:\.\d+)?)*$/D', $value)) {
                continue;
            }
            if (in_array('8', array_map('trim', explode(',', $value)), true)) {
                return true;
            }
        }

        return false;
    }
}
