<?php

declare(strict_types=1);

namespace App\Modules\Telephony\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class WavePromptValidator
{
    public function validate(UploadedFile $file): void
    {
        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            $this->invalid();
        }
        try {
            $length = filesize($file->getRealPath());
            $header = fread($stream, 12);
            if (strlen($header) !== 12 || substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WAVE') {
                $this->invalid();
            }
            $format = null;
            $dataSize = 0;
            while (ftell($stream) + 8 <= $length) {
                $chunk = fread($stream, 8);
                $size = unpack('Vsize', substr($chunk, 4))['size'];
                $next = ftell($stream) + $size + ($size % 2);
                if ($next > $length || $size > 20971520) {
                    $this->invalid();
                }
                if (substr($chunk, 0, 4) === 'fmt ') {
                    if ($size < 16) {
                        $this->invalid();
                    }
                    $format = unpack('vcodec/vchannels/Vrate/Vbytes/vblock/vbits', fread($stream, 16));
                }
                if (substr($chunk, 0, 4) === 'data') {
                    $dataSize = $size;
                }
                fseek($stream, $next);
            }
            if (! $format || $format['codec'] !== 1 || ! in_array($format['channels'], [1, 2], true)
                || $format['bits'] !== 16 || $format['rate'] < 8000 || $format['rate'] > 48000
                || $dataSize === 0 || $dataSize % ($format['channels'] * 2) !== 0
                || $dataSize > $format['rate'] * $format['channels'] * 2 * 600) {
                $this->invalid();
            }
        } finally {
            fclose($stream);
        }
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['audio' => 'فایل باید WAV سالم با صدای PCM شانزده‌بیتی، تک‌کاناله یا دوکاناله، نرخ ۸ تا ۴۸ کیلوهرتز و حداکثر ۱۰ دقیقه باشد.']);
    }
}
