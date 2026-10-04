<?php

declare(strict_types=1);

namespace App\\Modules\\Documents\\Infrastructure;

use App\\Modules\\Documents\\Contracts\\DocumentStorageContract;
use Illuminate\\Http\\UploadedFile;
use Illuminate\\Support\\Facades\\Storage;

class FilesystemDocumentStorage implements DocumentStorageContract
{
    public function store(UploadedFile $file, string $directory): array
    {
        $disk = (string) config('filesystems.default');
        $path = $file->store($directory, $disk);

        if ($path === false) {
            throw new RuntimeException('ذخیره فایل سند انجام نشد.');
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'size' => (int) ($file->getSize() ?? 0),
            'mime_type' => $file->getMimeType(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ];
    }

    public function delete(string $disk, string $path): void
    {
        Storage::disk($disk)->delete($path);
    }
}