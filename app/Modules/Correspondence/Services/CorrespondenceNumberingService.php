<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Services;

use App\Core\Settings\Services\SystemSettingsService;
use App\Modules\Correspondence\Models\Correspondence;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CorrespondenceNumberingService
{
    public function __construct(private readonly SystemSettingsService $settings)
    {
    }

    public function nextNumber(Correspondence $correspondence): string
    {
        $config = $this->settings->get([
            'correspondence.numbering.format' => 'year',
            'correspondence.numbering.custom_order' => 'year,department,sequence',
            'correspondence.numbering.separator' => '/',
            'correspondence.numbering.padding' => '6',
        ]);

        $format = $config['correspondence.numbering.format'];
        $sequence = DB::transaction(function (): int {
            $counter = DB::table('correspondence_number_counters')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            if ($counter === null) {
                throw new \RuntimeException('Correspondence numbering counter is not initialized.');
            }

            $next = (int) $counter->last_number + 1;
            DB::table('correspondence_number_counters')
                ->where('id', 1)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return $next;
        });

        $year = (string) ((int) now()->format('Y') - 621);
        $departmentCode = $correspondence->department?->code;
        $parts = [
            'year' => $year,
            'department' => filled($departmentCode) ? (string) $departmentCode : 'ORG',
            'sequence' => str_pad((string) $sequence, max(1, min(12, (int) $config['correspondence.numbering.padding'])), '0', STR_PAD_LEFT),
        ];

        $separator = (string) $config['correspondence.numbering.separator'];
        if (mb_strlen($separator) > 5) {
            throw new InvalidArgumentException('جداکننده شماره نامه حداکثر ۵ نویسه است.');
        }

        return match ($format) {
            'simple' => $parts['sequence'],
            'year' => implode($separator, [$parts['year'], $parts['sequence']]),
            'year_department' => implode($separator, [$parts['year'], $parts['department'], $parts['sequence']]),
            'custom' => $this->formatCustom((string) $config['correspondence.numbering.custom_order'], $parts, $separator),
            default => throw new InvalidArgumentException('قالب شماره‌گذاری مکاتبات نامعتبر است.'),
        };
    }

    /**
     * @param array<string, string> $parts
     */
    private function formatCustom(string $order, array $parts, string $separator): string
    {
        $tokens = array_map('trim', explode(',', $order));
        $allowed = ['year', 'department', 'sequence'];

        if (count($tokens) !== 3 || count(array_unique($tokens)) !== 3 || array_diff($tokens, $allowed) !== []) {
            throw new InvalidArgumentException('ترتیب سفارشی شماره نامه نامعتبر است.');
        }

        return implode($separator, array_map(static fn (string $token): string => $parts[$token], $tokens));
    }
}
