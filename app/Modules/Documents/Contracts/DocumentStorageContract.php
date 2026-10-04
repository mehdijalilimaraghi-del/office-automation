<?php

declare(strict_types=1);

namespace App\Modules\Documents\Contracts;

use Illuminate\Http\UploadedFile;

interface DocumentStorageContract
{
    /**
     * @return array{disk: string, path: string, size: int, mime_type: string|null, checksum: string}
     */
    public function store(UploadedFile $file, string $directory): array;

    public function delete(string $disk, string $path): void;
}