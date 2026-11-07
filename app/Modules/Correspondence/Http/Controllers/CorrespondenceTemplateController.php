<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Http\Controllers;

use App\Core\Settings\Services\SystemSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CorrespondenceTemplateController extends Controller
{
    private const BODY_TEMPLATE = ['id' => 'letter_body', 'kind' => 'text', 'role' => 'body', 'spacing' => 42, 'label' => 'متن اصلی نامه', 'content' => '', 'x' => 8, 'y' => 0, 'width' => 84, 'font_size' => 12, 'font_family' => 'default', 'page_format' => 'number_of', 'bold' => false, 'align' => 'right', 'visible' => true];

    private const DEFAULT_TEMPLATE = [
        ['id' => 'organization', 'kind' => 'text', 'role' => 'repeat', 'spacing' => 0, 'label' => 'نام سازمان', 'content' => '{{organization_name}}', 'x' => 8, 'y' => 5, 'width' => 58, 'height' => 7, 'font_size' => 20, 'bold' => true, 'align' => 'right', 'visible' => true],
        ['id' => 'contact', 'kind' => 'text', 'role' => 'repeat', 'spacing' => 0, 'label' => 'اطلاعات تماس', 'content' => 'آدرس: {{address}} | تلفن تماس: {{tel}} | ایمیل: {{email}}', 'x' => 8, 'y' => 14, 'width' => 84, 'height' => 8, 'font_size' => 9, 'bold' => false, 'align' => 'right', 'visible' => true],
        ['id' => 'letter_heading', 'kind' => 'text', 'role' => 'heading', 'spacing' => 8, 'label' => 'خطاب و موضوع', 'content' => '', 'x' => 8, 'y' => 0, 'width' => 84, 'font_size' => 12, 'bold' => true, 'align' => 'right', 'visible' => true],
        ['id' => 'letter_meta', 'kind' => 'text', 'role' => 'meta', 'spacing' => 0, 'label' => 'شماره و تاریخ', 'content' => "شماره: {{letter_number}}\nتاریخ: {{date}}", 'x' => 72, 'y' => 5, 'width' => 20, 'height' => 15, 'font_size' => 10, 'font_family' => 'default', 'page_format' => 'number_of', 'bold' => false, 'align' => 'left', 'visible' => true],
        self::BODY_TEMPLATE,
    ];

    public function edit(SystemSettingsService $settings): Response
    {
        $raw = $settings->get(['correspondence.print.template' => null])['correspondence.print.template'];
        $template = is_string($raw) ? json_decode($raw, true) : null;

        $elements = self::DEFAULT_TEMPLATE;
        if (is_array($template)) {
            $savedElements = array_filter($template, static fn (array $item): bool => ($item['kind'] ?? null) !== 'letterhead');
            $elements = array_values(array_map(
                static fn (array $item): array => [
                    ...$item,
                    'kind' => ($item['kind'] ?? 'text') === 'letterhead' ? 'text' : ($item['kind'] ?? 'text'),
                    'role' => $item['role'] ?? (in_array(($item['kind'] ?? null), ['stamp', 'signature'], true) ? 'issuer' : ((float) ($item['y'] ?? 0) < 25 ? 'repeat' : 'meta')),
                    'spacing' => $item['spacing'] ?? 0,
                    'font_family' => $item['font_family'] ?? 'default',
                    'page_format' => $item['page_format'] ?? 'number_of',
                ],
                $savedElements,
            ));
        }
        if (! collect($elements)->contains(static fn (array $item): bool => ($item['role'] ?? null) === 'body')) {
            $elements[] = self::BODY_TEMPLATE;
        }

        $organization = $settings->get([
            'organization.name' => null,
            'organization.short_name' => null,
            'organization.address' => null,
            'organization.phone' => null,
            'organization.email' => null,
            'organization.website' => null,
            'organization.stamp.path' => null,
            'organization.stamp.active' => '0',
        ]);
        $previewValues = [
            'organization_name' => $organization['organization.name'] ?: 'نام سازمان شما',
            'short_name' => $organization['organization.short_name'] ?: 'نام اختصاری',
            'address' => $organization['organization.address'] ?: 'نشانی سازمان',
            'tel' => $organization['organization.phone'] ?: '۰۲۱-۱۲۳۴۵۶۷۸',
            'email' => $organization['organization.email'] ?: 'info@example.com',
            'website' => $organization['organization.website'] ?: 'www.example.com',
            'letter_number' => '۱۴۰۵/۰۰۱۲',
            'registration_number' => '۱۴۰۵/۰۰۰۸',
            'date' => 'تاریخ نمونه',
            'recipient_name' => 'نام مخاطب',
            'recipient_organization' => 'سازمان مخاطب',
            'recipient_unit' => 'واحد مخاطب',
            'recipient_position' => 'سمت مخاطب',
            'recipient_title' => 'با سلام و احترام',
            'subject' => 'موضوع نمونه نامه',
            'letter_type' => 'نامه صادره',
            'creator_name' => 'نام تنظیم‌کننده',
            'creator_unit_title' => 'واحد صادرکننده',
        ];

        return Inertia::render('Correspondence/Template', [
            'elements' => $elements,
            'previewValues' => $previewValues,
            'previewBody' => implode("\n", [
                'با سلام و احترام؛',
                'پیرو موضوع مطرح‌شده، به استحضار می‌رساند متن این نامه به‌صورت نمونه برای بررسی چیدمان، قلم و فاصله‌گذاری درج شده است.',
                '',
                'خواهشمند است دستور فرمایید اقدامات لازم در این خصوص انجام شود.',
                '',
                'با سپاس',
            ]),
            'stampDataUri' => $organization['organization.stamp.active'] === '1'
                ? $this->imageDataUri($organization['organization.stamp.path'])
                : null,
            'defaultFontFamily' => $settings->get(['correspondence.composition.font_family' => 'Vazirmatn'])['correspondence.composition.font_family'],
            'letterheadDataUri' => $this->imageDataUri($settings->get(['organization.letterhead.path' => null])['organization.letterhead.path']),
            'tokens' => [
                ['key' => '{{organization_name}}', 'label' => 'نام سازمان'],
                ['key' => '{{short_name}}', 'label' => 'نام اختصاری'],
                ['key' => '{{address}}', 'label' => 'آدرس'],
                ['key' => '{{tel}}', 'label' => 'تلفن'],
                ['key' => '{{email}}', 'label' => 'ایمیل'],
                ['key' => '{{website}}', 'label' => 'وب‌سایت'],
                ['key' => '{{letter_number}}', 'label' => 'شماره نامه'],
                ['key' => '{{registration_number}}', 'label' => 'شماره ثبت'],
                ['key' => '{{date}}', 'label' => 'تاریخ'],
                ['key' => '{{recipient_name}}', 'label' => 'نام مخاطب'],
                ['key' => '{{recipient_organization}}', 'label' => 'سازمان مخاطب'],
                ['key' => '{{recipient_unit}}', 'label' => 'واحد مخاطب'],
                ['key' => '{{recipient_position}}', 'label' => 'سمت مخاطب'],
                ['key' => '{{recipient_title}}', 'label' => 'عنوان مخاطب'],
                ['key' => '{{subject}}', 'label' => 'موضوع نامه'],
                ['key' => '{{letter_type}}', 'label' => 'نوع نامه'],
                ['key' => '{{creator_name}}', 'label' => 'نام تنظیم‌کننده'],
                ['key' => '{{creator_unit_title}}', 'label' => 'عنوان واحد صادرکننده'],
            ],
        ]);
    }

    public function update(Request $request, SystemSettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'elements' => ['present', 'array', 'max:30'],
            'elements.*.kind' => ['required', 'in:text,stamp,signature,letterhead'],
            'elements.*.role' => ['nullable', 'in:meta,heading,page_number,issuer,repeat,body'],
            'elements.*.spacing' => ['nullable', 'numeric', 'min:0', 'max:80'],
            'elements.*.font_family' => ['nullable', 'in:default,Vazirmatn,Tahoma,Arial,B Nazanin,B Mitra,IRANSans,Times New Roman'],
            'elements.*.page_format' => ['nullable', 'in:number_of,slash,short,page'],
            'elements.*.id' => ['required', 'string', 'max:40', 'distinct'],
            'elements.*.label' => ['required', 'string', 'max:80'],
            'elements.*.content' => ['nullable', 'string', 'max:2000'],
            'elements.*.x' => ['required', 'numeric', 'min:0', 'max:100'],
            'elements.*.y' => ['required', 'numeric', 'min:0', 'max:100'],
            'elements.*.width' => ['required', 'numeric', 'min:5', 'max:100'],
            
            'elements.*.font_size' => ['required', 'integer', 'min:7', 'max:48'],
            'elements.*.bold' => ['required', 'boolean'],
            'elements.*.align' => ['required', 'in:right,center,left'],
            'elements.*.visible' => ['required', 'boolean'],
        ]);

        $elements = array_map(static fn (array $element): array => [
            ...$element,
            'kind' => ($element['kind'] ?? 'text') === 'letterhead' ? 'text' : ($element['kind'] ?? 'text'),
            'role' => $element['role'] ?? 'repeat',
            'spacing' => $element['spacing'] ?? 0,
            'font_family' => $element['font_family'] ?? 'default',
            'page_format' => $element['page_format'] ?? 'number_of',
            'content' => $element['content'] ?? '',
            'x' => round((float) $element['x'], 2),
            'y' => round((float) $element['y'], 2),
            'width' => round((float) $element['width'], 2),
        ], $data['elements']);

        abort_if(collect($elements)->where('role', 'body')->count() > 1, 422, 'فقط یک بخش برای متن اصلی نامه مجاز است.');

        foreach ($elements as $element) {
            abort_if($element['x'] + $element['width'] > 100.000001 || $element['y'] > 100, 422, 'موقعیت یا عرض المان از محدوده صفحه خارج است.');
        }

        $settings->set(['correspondence.print.template' => json_encode($elements, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);

        return back()->with('success', 'قالب نامه ذخیره شد.');
    }

    private function imageDataUri(?string $path): ?string
    {
        if (! filled($path) || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('local')->mimeType($path);
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($path));
    }
}
