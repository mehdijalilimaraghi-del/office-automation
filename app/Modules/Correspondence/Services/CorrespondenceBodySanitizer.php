<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Services;

final class CorrespondenceBodySanitizer
{

    public function hasRichContent(string $html): bool
    {
        return trim(strip_tags($html)) !== ''
            || preg_match('/<(?:img|table|hr)\\b|<div\\b[^>]*\\bdata-editor-shape=/i', $html) === 1;
    }

    public function sanitizeBody(string $body): string
    {
        $allowedTags = '<p><div><br><strong><b><em><i><u><s><strike><sup><sub><ul><ol><li><h2><h3><blockquote><table><thead><tbody><tr><th><td><span><hr><img><a>';
        $body = strip_tags($body, $allowedTags);

        return (string) preg_replace_callback(
            '/<(\/?)(p|div|br|strong|b|em|i|u|s|strike|sup|sub|ul|ol|li|h2|h3|blockquote|table|thead|tbody|tr|th|td|span|hr|img|a)\b([^>]*)>/i',
            function (array $match): string {
                $closing = $match[1];
                $tag = strtolower($match[2]);
                $attributes = $match[3];

                if ($closing === '/') return '</'.$tag.'>';
                if (in_array($tag, ['br', 'hr'], true)) return '<'.$tag.'>';

                if ($tag === 'a') {
                    if (! preg_match('/\bhref\s*=\s*["\x27](https?:\/\/[^"\x27]+|mailto:[^"\x27]+)["\x27]/i', $attributes, $hrefMatch)) return '';
                    return '<a href="'.e($hrefMatch[1]).'" rel="noopener noreferrer">';
                }

                if ($tag === 'img') {
                    if (! preg_match('/\bsrc\s*=\s*["\x27](data:image\/(?:png|jpeg|webp);base64,[A-Za-z0-9+\/=\s]+)["\x27]/i', $attributes, $srcMatch)) return '';
                    $src = preg_replace('/\s+/', '', $srcMatch[1]);
                    if (strlen($src) > 300000) return '';
                    $alt = preg_match('/\balt\s*=\s*["\x27]([^"\x27]*)["\x27]/i', $attributes, $altMatch)
                        ? e(mb_substr(strip_tags($altMatch[1]), 0, 250)) : '';
                    $width = preg_match('/\bwidth\s*=\s*["\x27]?(\d{1,4})["\x27]?/i', $attributes, $widthMatch)
                        ? min(1200, max(1, (int) $widthMatch[1])) : null;
                    $height = preg_match('/\bheight\s*=\s*["\x27]?(\d{1,4})["\x27]?/i', $attributes, $heightMatch)
                        ? min(1200, max(1, (int) $heightMatch[1])) : null;
                    $imageStyle = preg_match('/\bstyle\s*=\s*["\x27]([^"\x27]*)["\x27]/i', $attributes, $imageStyleMatch)
                        ? $this->sanitizeEditorStyle($imageStyleMatch[1]) : '';
                    return '<img src="'.$src.'" alt="'.$alt.'"'
                        .($width !== null ? ' width="'.$width.'"' : '')
                        .($height !== null ? ' height="'.$height.'"' : '')
                        .($imageStyle !== '' ? ' style="'.e($imageStyle).'"' : '').'>';
                }

                $direction = preg_match('/\bdir\s*=\s*["\x27](rtl|ltr)["\x27]/i', $attributes, $dirMatch)
                    ? strtolower($dirMatch[1]) : null;
                $style = preg_match('/\bstyle\s*=\s*["\x27]([^"\x27]*)["\x27]/i', $attributes, $styleMatch)
                    ? $this->sanitizeEditorStyle($styleMatch[1]) : '';
                $shape = preg_match('/\bdata-editor-shape\s*=\s*["\x27](rectangle|ellipse|line|arrow)["\x27]/i', $attributes, $shapeMatch)
                    ? $shapeMatch[1] : null;
                $colspan = preg_match('/\bcolspan\s*=\s*["\x27]?(\d{1,2})["\x27]?/i', $attributes, $colspanMatch)
                    ? min(20, max(1, (int) $colspanMatch[1])) : null;
                $rowspan = preg_match('/\browspan\s*=\s*["\x27]?(\d{1,2})["\x27]?/i', $attributes, $rowspanMatch)
                    ? min(20, max(1, (int) $rowspanMatch[1])) : null;

                return '<'.$tag
                    .($direction !== null ? ' dir="'.$direction.'"' : '')
                    .($style !== '' ? ' style="'.e($style).'"' : '')
                    .($shape !== null ? ' data-editor-shape="'.$shape.'"' : '')
                    .($colspan !== null ? ' colspan="'.$colspan.'"' : '')
                    .($rowspan !== null ? ' rowspan="'.$rowspan.'"' : '').'>';
            },
            $body,
        );
    }

    private function sanitizeEditorStyle(string $style): string
    {
        $allowed = [
            'text-align' => '/^(left|right|center|justify)$/i',
            'font-family' => '/^(Vazirmatn|Arial|Tahoma|B Nazanin|B Mitra|IRANSans|Times New Roman)$/i',
            'font-size' => '/^(8|9|1[0-9]|2[0-9]|3[0-6])(pt|px)$/i',
            'font-weight' => '/^(normal|bold|[1-9]00)$/i',
            'font-style' => '/^(normal|italic)$/i',
            'text-decoration' => '/^(none|underline|line-through|underline line-through)$/i',
            'color' => '/^#[0-9a-f]{3,8}$/i',
            'background-color' => '/^#[0-9a-f]{3,8}$/i',
            'line-height' => '/^(1|1\.[0-9]{1,2}|2|2\.[0-9]{1,2}|3)$/',
            'margin' => '/^\d{1,3}(px|pt|mm|%)$/i',
            'margin-top' => '/^\d{1,3}(px|pt|mm|%)$/i',
            'margin-bottom' => '/^\d{1,3}(px|pt|mm|%)$/i',
            'margin-left' => '/^(auto|\d{1,3}(px|pt|mm|%))$/i',
            'margin-right' => '/^(auto|\d{1,3}(px|pt|mm|%))$/i',
            'margin-inline-start' => '/^\d{1,3}px$/i',
            'padding' => '/^\d{1,3}(px|pt|mm|%)$/i',
            'padding-top' => '/^\d{1,3}(px|pt|mm|%)$/i',
            'padding-bottom' => '/^\d{1,3}(px|pt|mm|%)$/i',
            'padding-left' => '/^\d{1,3}(px|pt|mm|%)$/i',
            'padding-right' => '/^\d{1,3}(px|pt|mm|%)$/i',
            'width' => '/^\d{1,4}(px|mm|%)$/i',
            'height' => '/^(auto|\d{1,4}(px|mm|%))$/i',
            'max-width' => '/^\d{1,4}(px|mm|%)$/i',
            'border' => '/^(0|[1-3]px) (solid|dashed|dotted) #[0-9a-f]{3,8}$/i',
            'border-color' => '/^#[0-9a-f]{3,8}$/i',
            'border-collapse' => '/^(collapse|separate)$/i',
            'border-radius' => '/^(0|50%|\d{1,3}px)$/i',
            'vertical-align' => '/^(top|middle|bottom)$/i',
            'object-fit' => '/^(contain|cover)$/i',
            'display' => '/^(block|inline-block)$/i',
            'text-indent' => '/^-?\d{1,3}(px|pt|mm|%)$/i',
        ];
        $safe = [];
        foreach (explode(';', $style) as $declaration) {
            if (! str_contains($declaration, ':')) continue;
            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (isset($allowed[$property]) && preg_match($allowed[$property], $value) === 1) {
                $safe[] = $property.': '.$value;
            }
        }
        return implode('; ', $safe);
    }
}
