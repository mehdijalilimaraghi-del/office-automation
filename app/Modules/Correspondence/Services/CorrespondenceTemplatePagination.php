<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Services;

final class CorrespondenceTemplatePagination
{
    /**
     * Preserve legacy appendix numbering at the letter counter positions until
     * the owner explicitly saves independent appendix numbering configuration.
     *
     * @param array<int, array<string, mixed>> $elements
     * @return array<int, array<string, mixed>>
     */
    public static function withLegacyAppendixDefaults(array $elements, bool $configured): array
    {
        if ($configured || collect($elements)->contains(fn (array $item): bool => ($item['role'] ?? null) === 'appendix_page_number')) {
            return $elements;
        }

        $ids = array_column($elements, 'id');
        foreach ($elements as $element) {
            if (($element['role'] ?? null) !== 'page_number') {
                continue;
            }

            $id = 'appendix_page_number';
            for ($suffix = 1; in_array($id, $ids, true); $suffix++) {
                $id = 'appendix_page_number_'.$suffix;
            }
            $ids[] = $id;
            $elements[] = [
                ...$element,
                'id' => $id,
                'kind' => 'text',
                'role' => 'appendix_page_number',
                'label' => 'شماره صفحه پیوست',
                'content' => '',
                'page_format' => 'page',
            ];
        }

        return $elements;
    }
}