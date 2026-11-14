<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Services;

use App\Core\Settings\Services\SystemSettingsService;
use App\Models\User;
use App\Core\ExternalParties\Models\ExternalContact;
use App\Modules\Correspondence\Models\Correspondence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use InvalidArgumentException;

class CorrespondenceNumberingService
{
    public function __construct(private readonly SystemSettingsService $settings)
    {
    }

    public function nextNumber(Correspondence $correspondence): string
    {
        $config = $this->configuration();
        if (($config['format'] ?? '') === 'template') {
            return DB::transaction(fn (): string => $this->nextTemplateNumber($correspondence, $config, 'letter'));
        }
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

            if ($locked->type === Correspondence::TYPE_INCOMING && blank($locked->letter_number)) {
                throw new InvalidArgumentException('شماره نامه وارده باید عیناً از روی نامه اصلی وارد شود.');
            }


            if ($locked->type !== Correspondence::TYPE_INCOMING) {
                $config = $this->configuration();
                if (blank($locked->letter_number)) {
                    if (($config['format'] ?? '') === 'template') {
                        $locked->letter_number = $this->nextTemplateNumber($locked, $config, 'letter');
                    } else {
                        $this->assertFormatIsValid($config['format'], $config['custom_order']);
                        $this->assertSeparator($config['separator']);
                        $locked->letter_number = $this->formatNumber($locked, $this->incrementLetterCounter(), $config);
                    }
                }
            }
            if ($locked->type === Correspondence::TYPE_OUTGOING && $locked->recipient_addressing_snapshot === null) {
                $contact = $locked->external_contact_id === null
                    ? null
                    : ExternalContact::query()->whereKey($locked->external_contact_id)->first();
                $locked->loadMissing(['externalOrganization', 'externalDepartment.managerContact']);
                $unit = $locked->externalDepartment;
                $unitManager = $unit?->managerContact;
                $locked->recipient_addressing_snapshot = [
                    'organizational_title_prefix' => $unit?->organizational_title_prefix ?? $contact?->organizational_title_prefix,
                    'position' => $unit?->position ?? $contact?->position,
                    'personal_salutation' => $unit?->personal_salutation ?? $contact?->personal_salutation,
                    'professional_title' => $unit?->professional_title ?? $contact?->professional_title,
                    'name' => $unitManager?->name ?? $contact?->name ?? $locked->recipient_name,
                    'organization_name' => $locked->externalOrganization?->name,
                    'department_name' => $unit?->name,
                    'manager_contact_id' => $unitManager?->id,
                ];
            }

            if ($locked->type === Correspondence::TYPE_OUTGOING && $locked->document_identity_snapshot === null) {
                $this->snapshotDocumentIdentity($locked);
            }

            $registrationConfig = $this->registrationConfiguration();
            if (($registrationConfig['format'] ?? '') === 'template') {
                $registrationNumber = $this->nextTemplateNumber($locked, $registrationConfig, 'registration');
            } else {
                $this->assertFormatIsValid($registrationConfig['format'], $registrationConfig['custom_order']);
                $this->assertSeparator($registrationConfig['separator']);
                $sequence = $this->incrementCounter();
                $registrationNumber = $this->formatNumber($locked, $sequence, $registrationConfig);
            }

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
     * Allocate a number from a user-defined template. The scope key is derived
     * only from date/unit variables actually present in the template.
     *
     * @param array<string, mixed> $config
     */
    private function nextTemplateNumber(Correspondence $correspondence, array $config, string $counterType): string
    {
        $template = trim((string) ($config['template'] ?? ''));
        if ($template === '' || mb_strlen($template) > 200) {
            throw new InvalidArgumentException('قالب شماره‌گذاری باید بین ۱ تا ۲۰۰ نویسه باشد.');
        }
        preg_match_all('/\\{\\{([^{}]+)\\}\\}/u', $template, $matches);
        $tokens = $matches[1] ?? [];
        $numberTokens = array_values(array_filter($tokens, static fn (string $token): bool => preg_match('/^Num-(?:[1-9]|1[0-2])-([rc])$/', $token) === 1));
        if (count($numberTokens) !== 1 || count($tokens) !== count(array_unique($tokens))) {
            throw new InvalidArgumentException('قالب باید دقیقاً یک متغیر شمارنده معتبر و متغیرهای یکتای مجاز داشته باشد.');
        }
        $allowed = ['pdd','PDD','pmm','PMM','pyy','PYY','gdd','GDD','gmm','GMM','gyy','GYY','Dept'];
        foreach ($tokens as $token) {
            if (! in_array($token, $allowed, true) && ! preg_match('/^Num-(?:[1-9]|1[0-2])-[rc]$/', $token)) {
                throw new InvalidArgumentException('یکی از متغیرهای قالب شماره‌گذاری ناشناخته است: '.$token);
            }
        }

        $now = now();
        $values = $this->templateDateValues($now);
        $departmentCode = $correspondence->createdDepartment()->value('code');
        $values['Dept'] = filled($departmentCode) ? (string) $departmentCode : 'ORG';
        $numberToken = $numberTokens[0];
        [$unused, $length, $mode] = explode('-', $numberToken);
        $scopeValues = [];
        foreach ($tokens as $token) {
            if ($token !== $numberToken) {
                $scopeValues[$token] = $values[$token] ?? '';
            }
        }
        $scope = $mode === 'c' ? 'continuous' : hash('sha256', json_encode($scopeValues, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $table = DB::table('correspondence_numbering_scoped_counters');
        // Serialize allocations per number type, even when two active templates
        // produce different scopes but could render the same final number.
        $table->insertOrIgnore(['counter_type' => $counterType, 'scope_key' => '__global_lock__', 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $table->where('counter_type', $counterType)->where('scope_key', '__global_lock__')->lockForUpdate()->first();
        $table->insertOrIgnore(['counter_type' => $counterType, 'scope_key' => $scope, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $counter = $table->where('counter_type', $counterType)->where('scope_key', $scope)->lockForUpdate()->first();
        if ($counter === null) {
            throw new RuntimeException('شمارنده شماره‌گذاری قابل قفل‌گذاری نیست.');
        }

        $sequence = (int) $counter->last_number;
        do {
            $sequence++;
            $number = preg_replace_callback('/\\{\\{([^{}]+)\\}\\}/u', function (array $match) use ($values, $length, $sequence): string {
                if (str_starts_with($match[1], 'Num-')) {
                    return str_pad((string) $sequence, (int) $length, '0', STR_PAD_LEFT);
                }
                return (string) ($values[$match[1]] ?? '');
            }, $template);
            $column = $counterType === 'letter' ? 'letter_number' : 'registration_number';
            $exists = Correspondence::query()->where($column, $number)->exists();
        } while ($exists && $sequence < 999999999);

        if ($exists) {
            throw new RuntimeException('امکان تخصیص شماره یکتای جدید وجود ندارد.');
        }
        $table->where('counter_type', $counterType)->where('scope_key', $scope)->update(['last_number' => $sequence, 'updated_at' => now()]);
        return (string) $number;
    }

    /** @return array<string, string> */
    private function templateDateValues(CarbonInterface $date): array
    {
        $gregorian = [
            'gdd' => $date->format('d'), 'GDD' => $date->format('j'),
            'gmm' => $date->format('m'), 'GMM' => $date->format('F'),
            'gyy' => $date->format('y'), 'GYY' => $date->format('Y'),
        ];
        $persian = [];
        if (class_exists(\IntlDateFormatter::class)) {
            $formatter = new \IntlDateFormatter('fa_IR@calendar=persian', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'Asia/Tehran', \IntlDateFormatter::TRADITIONAL, 'yyyy|MM|dd|MMMM|d');
            $formatted = $formatter->format($date->getTimestamp());
            if (is_string($formatted)) {
                [$year, $month, $day, $monthName, $dayNumber] = array_pad(explode('|', $formatted), 5, '');
                $persian = ['pyy' => substr($year, -2), 'PYY' => $year, 'pmm' => $month, 'PMM' => $monthName, 'pdd' => $day, 'PDD' => $dayNumber];
            }
        }
        if ($persian === []) {
            [$jalaliYear, $jalaliMonth, $jalaliDay] = $this->jalaliDateParts($date);
            $monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
            $persian = [
                'pyy' => substr((string) $jalaliYear, -2),
                'PYY' => (string) $jalaliYear,
                'pmm' => str_pad((string) $jalaliMonth, 2, '0', STR_PAD_LEFT),
                'PMM' => $monthNames[$jalaliMonth - 1],
                'pdd' => str_pad((string) $jalaliDay, 2, '0', STR_PAD_LEFT),
                'PDD' => (string) $jalaliDay,
            ];
        }
        return $gregorian + $persian;
    }

    private function snapshotDocumentIdentity(Correspondence $correspondence): void
    {
        $correspondence->loadMissing(['creator:id,name', 'createdDepartment']);
        $department = $correspondence->createdDepartment;
        $settings = $this->settings->get([
            'organization.name' => null,
            'organization.short_name' => null,
            'organization.address' => null,
            'organization.phone' => null,
            'organization.email' => null,
            'organization.website' => null,
            'organization.stamp.path' => null,
            'organization.stamp.active' => '0',
            'organization.letterhead.path' => null,
        ]);

        $snapshotPath = 'correspondence/'.$correspondence->id.'/print-snapshots';
        $stampPath = null;
        $letterheadPath = null;
        $signaturePath = null;

        if (filled($settings['organization.letterhead.path'])
            && Storage::disk('local')->exists($settings['organization.letterhead.path'])) {
            $extension = pathinfo($settings['organization.letterhead.path'], PATHINFO_EXTENSION) ?: 'png';
            $letterheadPath = $snapshotPath.'/organization-letterhead.'.$extension;
            if (! Storage::disk('local')->copy($settings['organization.letterhead.path'], $letterheadPath)) {
                throw new RuntimeException('تهیه تصویر ثابت سربرگ سازمان برای نامه انجام نشد.');
            }
        }

        if ($settings['organization.stamp.active'] === '1'
            && filled($settings['organization.stamp.path'])
            && Storage::disk('local')->exists($settings['organization.stamp.path'])) {
            $extension = pathinfo($settings['organization.stamp.path'], PATHINFO_EXTENSION) ?: 'png';
            $stampPath = $snapshotPath.'/organization-stamp.'.$extension;
            if (! Storage::disk('local')->copy($settings['organization.stamp.path'], $stampPath)) {
                throw new RuntimeException('تهیه تصویر ثابت مهر سازمان برای نامه انجام نشد.');
            }
        }

        if ($correspondence->workflow_state === 'approved_for_registration'
            && $department?->manager_signature_active
            && filled($department->manager_signature_path)
            && Storage::disk('local')->exists($department->manager_signature_path)) {
            $extension = pathinfo($department->manager_signature_path, PATHINFO_EXTENSION) ?: 'png';
            $signaturePath = $snapshotPath.'/department-manager-signature.'.$extension;
            if (! Storage::disk('local')->copy($department->manager_signature_path, $signaturePath)) {
                throw new RuntimeException('تهیه تصویر ثابت امضای مدیر واحد برای نامه انجام نشد.');
            }
        }

        $correspondence->document_identity_snapshot = [
            'creator_name' => $correspondence->creator?->name,
            'creator_unit_title' => $department?->name ? 'مدیریت '.$department->name : null,
            'department_name' => $department?->name,
            'organization' => [
                'name' => $settings['organization.name'],
                'short_name' => $settings['organization.short_name'],
                'address' => $settings['organization.address'],
                'phone' => $settings['organization.phone'],
                'email' => $settings['organization.email'],
                'website' => $settings['organization.website'],
            ],
            'stamp_path' => $stampPath,
            'letterhead_path' => $letterheadPath,
            'signature_path' => $signaturePath,
            'finalized_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{format: string, custom_order: string, separator: string, padding: int}
     */
    private function configuration(): array
    {
        $settings = $this->settings->get([
            'correspondence.numbering.format' => 'template',
            'correspondence.numbering.template' => '{{PYY}}/{{Dept}}/{{Num-6-r}}',
            'correspondence.numbering.custom_order' => 'year,department,sequence',
            'correspondence.numbering.separator' => '/',
            'correspondence.numbering.padding' => '6',
        ]);

        return [
            'format' => (string) $settings['correspondence.numbering.format'],
            'template' => (string) $settings['correspondence.numbering.template'],
            'custom_order' => (string) $settings['correspondence.numbering.custom_order'],
            'separator' => (string) $settings['correspondence.numbering.separator'],
            'padding' => max(4, min(12, (int) $settings['correspondence.numbering.padding'])),
        ];
    }

    /** @return array{format: string, custom_order: string, separator: string, padding: int} */
    private function registrationConfiguration(): array
    {
        $settings = $this->settings->get(['correspondence.registration_numbering.format'=>'template','correspondence.registration_numbering.template'=>'{{PYY}}/{{Num-6-r}}','correspondence.registration_numbering.custom_order'=>'year,department,sequence','correspondence.registration_numbering.separator'=>'/','correspondence.registration_numbering.padding'=>'6']);
        return ['format'=>(string)$settings['correspondence.registration_numbering.format'],'template'=>(string)$settings['correspondence.registration_numbering.template'],'custom_order'=>(string)$settings['correspondence.registration_numbering.custom_order'],'separator'=>(string)$settings['correspondence.registration_numbering.separator'],'padding'=>max(4,min(12,(int)$settings['correspondence.registration_numbering.padding']))];
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

    /** @return array{int, int, int} */
    private function jalaliDateParts(CarbonInterface $date): array
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
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return [$jy, $jm, $jd];
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
