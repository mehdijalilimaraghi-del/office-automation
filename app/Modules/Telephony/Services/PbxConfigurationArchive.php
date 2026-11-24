<?php

declare(strict_types=1);

namespace App\Modules\Telephony\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class PbxConfigurationArchive
{
    public function snapshot(): array
    {
        $configuration = app(PbxConfigurationService::class);
        $manifest = $configuration->manifest();
        $menu = $configuration->menu();
        $audio = null;
        if ($manifest['ivr']['audio_file']) {
            abort_unless(Storage::disk('local')->exists($menu['audio_path']), 409, 'فایل پیام راهنما در دسترس نیست.');
            $audio = Storage::disk('local')->get($menu['audio_path']);
        }
        $json = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $audioHash = $audio === null ? '' : hash('sha256', $audio);

        return ['json' => $json, 'audio' => $audio, 'revision' => hash('sha256', $json."\n".$audioHash)];
    }

    public function build(array $snapshot): string
    {
        abort_unless(class_exists(\ZipArchive::class), 503);
        File::ensureDirectoryExists(storage_path('app/private'));
        $file = tempnam(storage_path('app/private'), 'telephony-device-');
        $zip = new \ZipArchive;
        try {
            if ($zip->open($file, \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Cannot create device configuration package');
            }
            if (! $zip->addFromString('office-telephony.json', $snapshot['json'])) {
                throw new \RuntimeException('Cannot write configuration');
            }
            if ($snapshot['audio'] !== null && ! $zip->addFromString('greeting.wav', $snapshot['audio'])) {
                throw new \RuntimeException('Cannot write greeting');
            }
            if (! $zip->close()) {
                throw new \RuntimeException('Cannot finalize device configuration package');
            }
        } catch (\Throwable $error) {
            try {
                $zip->close();
            } catch (\Throwable) {
                // Preserve the original archive error.
            }
            @unlink($file);
            throw $error;
        }

        return $file;
    }
}
