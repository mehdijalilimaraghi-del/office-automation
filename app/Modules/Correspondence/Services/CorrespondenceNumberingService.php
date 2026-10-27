<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Services;

use App\Core\Settings\Services\SystemSettingsService;
use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CorrespondenceNumberingService
{
    public function __construct(private readonly SystemSettingsService $settings)
    {
    }

    public function nextNumber(Correspondence $correspondence): string
    {
        $config = $this->configuration();
        $this->assertFormatIsValid($config['format'], $config['custom_order']);
        $this->assertSeparator($config['separator']);

        $sequence = DB::transaction(fn (): int => $this->incrementLetterCounter());
        return $this->formatNumber($correspondence, $sequence, $config);
    }

    public function register(Correspondence $correspondence, User $registrar): Correspondence
    {
        return DB::transaction(function () use ($correspondence, $registrar): Correspondence {
            $locked = Correspondence::query()
                ->whereKey($correspondence->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! app(CorrespondenceAccessService::class)->canRegister($locked, $registrar)) {
                throw new InvalidArgumentException('ثبت نهایی این مکاتبه فقط برای دبیرخانه مجاز است؛ ثبت نامه داخلی فقط برای ایجادکننده آن مجاز است.');
            }

            if ($locked->status !== Correspondence::STATUS_DRAFT) {
                throw new InvalidArgumentException('فقط پیش‌نویس را می‌توان ثبت نهایی کرد.');
            }

            if (app(CorrespondenceCompositionSettings::class)->requiresApproval($locked->type)
                && in_array($locked->type, [Correspondence::TYPE_INTERNAL, Correspondence::TYPE_OUTGOING], true)
                && $locked->workflow_state !== 'approved_for_registration') {
                throw new InvalidArgumentException('این نامه باید پیش از ثبت نهایی توسط مدیر واحد تأیید شود.');
            }

            $config = $this->configuration();
            $this->assertFormatIsValid($config['format'], $config['custom_order']);
            $this->assertSeparator($config['separator']);

            if ($locked->type === Correspondence::TYPE_INCOMING && blank($locked->letter_number)) throw new InvalidArgumentException('شماره نامه وارده باید عیناً از روی نامه اصلی وارد شود.');
            if ($locked->type !== Correspondence::TYPE_INCOMING && blank($locked->letter_number)) $locked->letter_number = $this->formatNumber($locked, $this->incrementLetterCounter(), $config);
            $registrationConfig = $this->registrationConfiguration();
            $this->assertFormatIsValid($registrationConfig['format'], $registrationConfig['custom_order']);
            $this->assertSeparator($registrationConfig['separator']);
            $sequence = $this->incrementCounter();
            $registrationNumber = $this->formatNumber($locked, $sequence, $registrationConfig);

            $locked->forceFill([
                'status' => Correspondence::STATUS_REGISTERED,
                'registration_number' => $registrationNumber,
                'registered_at' => now(),
                'registered_by' => $registrar->id,
            ])->save();

            return $locked->refresh()->load(['creator', 'registrar', 'createdDepartment', 'recipientDepartment', 'replyTo']);
        });
    }

    /**
     * @return array{format: string, custom_order: string, separator: string, padding: int}
     */
    private function configuration(): array
    {
        $settings = $this->settings->get([
            'correspondence.numbering.format' => 'year',
            'correspondence.numbering.custom_order' => 'year,department,sequence',
            'correspondence.numbering.separator' => '/',
            'correspondence.numbering.padding' => '6',
        ]);

        return [
            'format' => (string) $settings['correspondence.numbering.format'],
            'custom_order' => (string) $settings['correspondence.numbering.custom_order'],
            'separator' => (string) $settings['correspondence.numbering.separator'],
            'padding' => max(4, min(12, (int) $settings['correspondence.numbering.padding'])),
        ];
    }

    /** @return array{format: string, custom_order: string, separator: string, padding: int} */
    private function registrationConfiguration(): array
    {
        $settings = $this->settings->get(['correspondence.registration_numbering.format'=>'year','correspondence.registration_numbering.custom_order'=>'year,department,sequence','correspondence.registration_numbering.separator'=>'/','correspondence.registration_numbering.padding'=>'6']);
        return ['format'=>(string)$settings['correspondence.registration_numbering.format'],'custom_order'=>(string)$settings['correspondence.registration_numbering.custom_order'],'separator'=>(string)$settings['correspondence.registration_numbering.separator'],'padding'=>max(4,min(12,(int)$settings['correspondence.registration_numbering.padding']))];
    }

    private function incrementLetterCounter(): int
    {
        $counter = DB::table('correspondence_letter_number_counters')
            ->where('id', 1)
            ->lockForUpdate()
            ->first();

        if ($counter === null) {
            throw new \RuntimeException('Correspondence numbering counter is not initialized.');
        }

        $next = (int) $counter->last_number + 1;
        DB::table('correspondence_letter_number_counters')
            ->where('id', 1)
            ->update(['last_number' => $next, 'updated_at' => now()]);

        return $next;
    }

    private function incrementCounter(): int
    {
        $counter = DB::table('correspondence_number_counters')
            ->where('id', 1)
            ->lockForUpdate()
            ->first();

        if ($counter === null) {
            throw new \RuntimeException('Correspondence registration counter is not initialized.');
        }

        $next = (int) $counter->last_number + 1;
        DB::table('correspondence_number_counters')
            ->where('id', 1)
            ->update(['last_number' => $next, 'updated_at' => now()]);

        return $next;
    }

    /**
     * @param array{format: string, custom_order: string, separator: string, padding: int} $config
     */
    private function formatNumber(Correspondence $correspondence, int $sequence, array $config): string
    {
        $departmentCode = $correspondence->createdDepartment()->value('code');
        $parts = [
            'year' => (string) $this->jalaliYear(now()),
            'department' => filled($departmentCode) ? (string) $departmentCode : 'ORG',
            'sequence' => str_pad((string) $sequence, $config['padding'], '0', STR_PAD_LEFT),
        ];

        return match ($config['format']) {
            'simple' => $parts['sequence'],
            'year' => implode($config['separator'], [$parts['year'], $parts['sequence']]),
            'year_department' => implode($config['separator'], [$parts['year'], $parts['department'], $parts['sequence']]),
            'custom' => $this->formatCustom($config['custom_order'], $parts, $config['separator']),
        };
    }

    private function assertSeparator(string $separator): void
    {
        if (mb_strlen($separator) > 5) {
            throw new InvalidArgumentException('جداکننده شماره نامه حداکثر ۵ نویسه است.');
        }
    }

    private function assertFormatIsValid(string $format, string $order): void
    {
        if (! in_array($format, ['simple', 'year', 'year_department', 'custom'], true)) {
            throw new InvalidArgumentException('قالب شماره‌گذاری مکاتبات نامعتبر است.');
        }

        if ($format === 'custom') {
            $tokens = array_map('trim', explode(',', $order));
            $allowed = ['year', 'department', 'sequence'];
            if (! in_array(count($tokens), [2, 3], true)
                || count(array_unique($tokens)) !== count($tokens)
                || array_diff($tokens, $allowed) !== []) {
                throw new InvalidArgumentException('ترتیب سفارشی شماره نامه باید شامل دو یا سه جزء متفاوت باشد.');
            }
        }
    }

    /**
     * @param array<string, string> $parts
     */
    private function formatCustom(string $order, array $parts, string $separator): string
    {
        $tokens = array_map('trim', explode(',', $order));
        return implode($separator, array_map(static fn (string $token): string => $parts[$token], $tokens));
    }

    private function jalaliYear(CarbonInterface $date): int
    {
        $gy = $date->year;
        $gm = $date->month;
        $gd = $date->day;
        $daysInGregorianMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

        if ($gy > 1600) {
            $jy = 979;
            $gy -= 1600;
        } else {
            $jy = 0;
            $gy -= 621;
        }

        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 365 * $gy
            + intdiv($gy2 + 3, 4)
            - intdiv($gy2 + 99, 100)
            + intdiv($gy2 + 399, 400)
            - 80
            + $gd
            + $daysInGregorianMonth[$gm - 1];

        $jy += 33 * intdiv($days, 12053);
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
        }

        return $jy;
    }
}
