<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Http\Controllers;

use App\Core\Settings\Services\SystemSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CorrespondenceTemplateController extends Controller
{
    private const DEFAULT_TEMPLATE = [
        ['id' => 'organization', 'kind' => 'text', 'label' => 'نام سازمان', 'content' => '{{organization_name}}', 'x' => 8, 'y' => 5, 'width' => 58, 'height' => 7, 'font_size' => 20, 'bold' => true, 'align' => 'right', 'visible' => true],
        ['id' => 'contact', 'kind' => 'text', 'label' => 'اطلاعات تماس', 'content' => 'آدرس: {{address}} | تلفن تماس: {{tel}} | ایمیل: {{email}}', 'x' => 8, 'y' => 14, 'width' => 84, 'height' => 8, 'font_size' => 9, 'bold' => false, 'align' => 'right', 'visible' => true],
        ['id' => 'letter_meta', 'kind' => 'text', 'label' => 'شماره و تاریخ', 'content' => "شماره: {{letter_number}}\nتاریخ: {{date}}", 'x' => 72, 'y' => 5, 'width' => 20, 'height' => 15, 'font_size' => 10, 'bold' => false, 'align' => 'left', 'visible' => true],
    ];

    public function edit(SystemSettingsService $settings): Response
    {
        $raw = $settings->get(['correspondence.print.template' => null])['correspondence.print.template'];
        $template = is_string($raw) ? json_decode($raw, true) : null;

        return Inertia::render('Correspondence/Template', [
            'elements' => is_array($template) ? $template : self::DEFAULT_TEMPLATE,
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
            'elements' => ['required', 'array', 'min:1', 'max:20'],
            'elements.*.kind' => ['required', 'in:text,stamp,signature'],
            'elements.*.id' => ['required', 'string', 'max:40', 'distinct'],
            'elements.*.label' => ['required', 'string', 'max:80'],
            'elements.*.content' => ['nullable', 'string', 'max:2000'],
            'elements.*.x' => ['required', 'numeric', 'min:0', 'max:100'],
            'elements.*.y' => ['required', 'numeric', 'min:0', 'max:100'],
            'elements.*.width' => ['required', 'numeric', 'min:5', 'max:100'],
            'elements.*.height' => ['required', 'numeric', 'min:3', 'max:80'],
            'elements.*.font_size' => ['required', 'integer', 'min:7', 'max:48'],
            'elements.*.bold' => ['required', 'boolean'],
            'elements.*.align' => ['required', 'in:right,center,left'],
            'elements.*.visible' => ['required', 'boolean'],
        ]);

        $elements = array_map(static fn (array $element): array => [...$element, 'content' => $element['content'] ?? ''], $data['elements']);

        foreach ($elements as $element) {
            abort_if($element['x'] + $element['width'] > 100 || $element['y'] + $element['height'] > 100, 422, 'ابعاد کادر از محدوده صفحه خارج است.');
        }

        $settings->set(['correspondence.print.template' => json_encode($elements, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);

        return back()->with('success', 'قالب نامه ذخیره شد.');
    }
}
