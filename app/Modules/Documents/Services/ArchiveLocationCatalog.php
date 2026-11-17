<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Core\Settings\Services\SystemSettingsService;
use Illuminate\Validation\ValidationException;

class ArchiveLocationCatalog
{
    private const KEY = 'archive.storage_locations';

    public function __construct(private readonly SystemSettingsService $settings) {}

    public function paths(): array
    {
        $locations = json_decode((string) $this->settings->get([self::KEY => '[]'])[self::KEY], true);

        return is_array($locations)
            ? array_values(array_map(static fn (array $parts): string => implode(' / ', $parts), $locations))
            : [];
    }

    public function save(string $configuration): void
    {
        $lines = preg_split('/\R/u', trim($configuration)) ?: [];
        $locations = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = array_map('trim', explode('/', $line));
            $path = implode(' / ', $parts);
            if (count($parts) < 2 || count($parts) > 4 || in_array('', $parts, true) || mb_strlen($path) > 255) {
                throw ValidationException::withMessages(['locations' => 'هر محل باید دو تا چهار بخش داشته باشد، مانند بایگانی / اتاق / قفسه / زونکن.']);
            }
            $locations[$path] = $parts;
        }

        $this->settings->set([self::KEY => json_encode(array_values($locations), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
    }

    public function validatePath(?string $path, string $field): void
    {
        $locations = $this->paths();
        if ($locations !== [] && ! in_array($path, $locations, true)) {
            throw ValidationException::withMessages([$field => 'محل نگهداری را از فهرست انتخاب کنید.']);
        }
    }
}
