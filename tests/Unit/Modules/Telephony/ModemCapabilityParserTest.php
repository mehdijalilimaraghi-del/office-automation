<?php

namespace Tests\Unit\Modules\Telephony;

use App\Modules\Telephony\Services\ModemCapabilityParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ModemCapabilityParserTest extends TestCase
{
    #[DataProvider('responses')]
    public function test_voice_class_recognition_requires_an_exact_successful_class_response(string $response, bool $expected): void
    {
        $this->assertSame($expected, (new ModemCapabilityParser)->advertisesVoice($response));
    }

    public static function responses(): array
    {
        return [
            'lsi actual unprefixed reply' => ["AT+FCLASS=?\r\r\n0,1,8\r\n\r\nOK\r\n", true],
            'prefixed reply' => ["+FCLASS: 0,1,8\r\nOK", true],
            'parenthesized reply' => ["+FCLASS: (0,1,8)\r\nOK", true],
            'whitespace and decimal fax class' => [" 0, 1.0, 8 \n OK \n", true],
            'single voice class' => ["8\r\nOK", true],
            'fax only' => ["0,1\r\nOK", false],
            'class eighteen' => ["0,1,18\r\nOK", false],
            'class eighty' => ["0,1,80\r\nOK", false],
            'incomplete response' => ['0,1,8', false],
            'failed response' => ["0,1,8\r\nERROR", false],
            'contradictory error' => ["0,1,8\r\nERROR\r\nOK", false],
            'firmware version is not a class' => ["LSI SoftModem Version 2.2.98\r\nOK", false],
            'codec rate is not a class' => ["8000,16\r\nOK", false],
            'empty response' => ['', false],
        ];
    }
}
