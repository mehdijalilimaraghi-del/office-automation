<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Core\Settings\Services\SystemSettingsService;

class ArchiveApprovalPolicy
{
    private const KEY = 'archive.checkout_without_approval_categories';

    public function __construct(private readonly SystemSettingsService $settings) {}

    /** @return list<string> */
    public function exceptions(): array
    {
        $stored = $this->settings->get([self::KEY => '[]'])[self::KEY];
        $categories = json_decode((string) $stored, true);

        return is_array($categories) && array_is_list($categories) ? array_values(array_filter($categories, 'is_string')) : [];
    }

    /** @param list<string> $categories */
    public function save(array $categories): void
    {
        $categories = array_map(static fn (string $category): string => mb_strtolower(trim($category)), $categories);
        $this->settings->set([self::KEY => json_encode(array_values(array_unique(array_filter($categories, 'strlen'))), JSON_THROW_ON_ERROR)]);
    }

    public function requiresApproval(?string $category): bool
    {
        return $category === null || ! in_array(mb_strtolower(trim($category)), $this->exceptions(), true);
    }
}
