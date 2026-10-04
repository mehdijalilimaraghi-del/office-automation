<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Models\User;
use App\Modules\Documents\Contracts\DocumentStorageContract;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentAccess;
use App\Modules\Documents\Models\DocumentLink;
use App\Modules\Documents\Models\DocumentVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class DocumentService
{
    public function __construct(private readonly DocumentStorageContract $storage) {}

    public function create(array $data, UploadedFile $file, User $creator, ?Model $linkable = null, ?string $purpose = null): Document
    {
        $stored = null;

        try {
            return DB::transaction(function () use ($data, $file, $creator, $linkable, $purpose, &$stored): Document {
                $document = Document::query()->create([
                    'title' => $data['title'],
                    'document_type' => $data['document_type'] ?? null,
                    'description' => $data['description'] ?? null,
                    'created_by' => $creator->id,
                    'is_active' => true,
                ]);

                $stored = $this->storage->store($file, 'documents/'.$document->id);

                $document->versions()->create([
                    'version_number' => 1,
                    'disk' => $stored['disk'],
                    'path' => $stored['path'],
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $stored['mime_type'],
                    'size' => $stored['size'],
                    'checksum' => $stored['checksum'],
                    'uploaded_by' => $creator->id,
                ]);

                if ($linkable !== null) {
                    $this->link($document, $linkable, $creator, $purpose);
                }

                return $document->refresh()->load(['creator', 'versions', 'links']);
            });
        } catch (Throwable $exception) {
            if (is_array($stored)) {
                $this->storage->delete($stored['disk'], $stored['path']);
            }
            throw $exception;
        }
    }

    public function addVersion(Document $document, UploadedFile $file, User $uploader): DocumentVersion
    {
        $stored = null;

        try {
            return DB::transaction(function () use ($document, $file, $uploader, &$stored): DocumentVersion {
                $versionNumber = ((int) $document->versions()->max('version_number')) + 1;
                $stored = $this->storage->store($file, 'documents/'.$document->id);

                return $document->versions()->create([
                    'version_number' => $versionNumber,
                    'disk' => $stored['disk'],
                    'path' => $stored['path'],
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $stored['mime_type'],
                    'size' => $stored['size'],
                    'checksum' => $stored['checksum'],
                    'uploaded_by' => $uploader->id,
                ]);
            });
        } catch (Throwable $exception) {
            if (is_array($stored)) {
                $this->storage->delete($stored['disk'], $stored['path']);
            }
            throw $exception;
        }
    }

    public function link(Document $document, Model $linkable, User $creator, ?string $purpose = null): DocumentLink
    {
        return DocumentLink::query()->firstOrCreate(
            [
                'document_id' => $document->id,
                'linkable_type' => $linkable->getMorphClass(),
                'linkable_id' => $linkable->getKey(),
            ],
            [
                'purpose' => $purpose,
                'created_by' => $creator->id,
            ],
        );
    }

    public function grantAccess(Document $document, User $user, User $grantedBy, string $accessLevel = 'view'): DocumentAccess
    {
        if (! in_array($accessLevel, ['view', 'edit'], true)) {
            throw new \InvalidArgumentException('سطح دسترسی سند نامعتبر است.');
        }

        return DocumentAccess::query()->updateOrCreate(
            ['document_id' => $document->id, 'user_id' => $user->id],
            ['access_level' => $accessLevel, 'granted_by' => $grantedBy->id],
        );
    }
}