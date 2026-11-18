<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Core\Settings\Services\OrganizationDepartmentRoleService;
use App\Core\Settings\Services\SystemSettingsService;
use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Services\CorrespondenceAccessService;
use App\Modules\Documents\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CorrespondenceArchiveService
{
    public function __construct(
        private readonly ArchiveAccessService $archive,
        private readonly CorrespondenceAccessService $correspondence,
        private readonly DocumentService $documents,
        private readonly SystemSettingsService $settings,
    ) {}

    public function authorize(Correspondence $letter, User $user): void
    {
        abort_unless($letter->type === Correspondence::TYPE_INCOMING
            && $letter->status === Correspondence::STATUS_REGISTERED
            && $this->correspondence->canView($letter, $user), 403);
    }

    public function entry(Correspondence $letter, User $user): ?string
    {
        if ($letter->type !== Correspondence::TYPE_INCOMING
            || $letter->status !== Correspondence::STATUS_REGISTERED
            || $letter->archive_document_id !== null
            || ! $this->correspondence->canView($letter, $user)
            || $this->archive->departments($user)->isEmpty()) {
            return null;
        }

        $central = app(OrganizationDepartmentRoleService::class)->activeDepartmentId(OrganizationDepartmentRoleService::CENTRAL_ARCHIVE);
        $route = $central !== null && $this->archive->departments($user)->contains('id', $central)
            ? 'archive.central.create' : 'archive.create';

        return route($route, ['correspondence_id' => $letter->id]);
    }

    public function context(Correspondence $letter): array
    {
        $details = array_filter([
            $letter->sender_name ? 'فرستنده: '.$letter->sender_name : null,
            $letter->letter_number ? 'شماره نامه: '.$letter->letter_number : null,
            $letter->registration_number ? 'شماره ثبت: '.$letter->registration_number : null,
        ]);
        $category = $this->settings->get(['correspondence.archive.incoming_category' => null]);

        return [
            'id' => $letter->id,
            'title' => $letter->subject,
            'description' => implode("\n", $details),
            'sender' => $letter->sender_name,
            'letter_number' => $letter->letter_number,
            'registration_number' => $letter->registration_number,
            'received_at' => $letter->received_at?->format('Y-m-d'),
            'related_department_id' => $letter->recipient_department_id !== null ? (int) $letter->recipient_department_id : null,
            'preferred_department_id' => $letter->created_department_id !== null ? (int) $letter->created_department_id : null,
            'default_category' => $category['correspondence.archive.incoming_category'],
            'confidential' => $letter->classification === Correspondence::CLASSIFICATION_CONFIDENTIAL,
            'files' => $letter->documents()->whereIn('purpose', [
                'correspondence_original', 'correspondence_attachment', 'correspondence_appendix',
            ])->with('document.latestVersion')->get()->filter(fn ($link) => $link->document?->latestVersion !== null)
                ->map(fn ($link) => [
                    'name' => $link->document->latestVersion->original_name,
                    'purpose' => $link->purpose,
                ])->values(),
        ];
    }

    public function register(Correspondence $letter, array $data, User $user): Document
    {
        return DB::transaction(function () use ($letter, $data, $user): Document {
            $locked = Correspondence::query()->whereKey($letter->id)->lockForUpdate()->firstOrFail();
            $this->authorize($locked, $user);
            if ($locked->archive_document_id !== null) {
                return Document::query()->findOrFail($locked->archive_document_id);
            }

            $links = $locked->documents()->whereIn('purpose', [
                'correspondence_original', 'correspondence_attachment', 'correspondence_appendix',
            ])->orderBy('document_id')->get();
            $files = [];
            $primary = null;
            foreach ($links as $link) {
                $source = Document::query()->whereKey($link->document_id)->lockForUpdate()->firstOrFail();
                $version = $source->latestVersion()->first();
                if ($version === null || ! Storage::disk($version->disk)->exists($version->path)) {
                    throw ValidationException::withMessages(['correspondence_id' => 'یکی از فایل‌های نامه در دسترس نیست. فایل‌های نامه را بررسی کنید.']);
                }
                if ($link->purpose === 'correspondence_original' && $primary === null) {
                    $primary = $version;
                }
                $files[] = [
                    ...$version->only(['disk', 'path', 'original_name', 'mime_type', 'size', 'checksum']),
                    'source_version_id' => $version->id,
                    'purpose' => $link->purpose,
                ];
            }
            if ($primary === null) {
                throw ValidationException::withMessages(['correspondence_id' => 'برای این نامه فایل اصل ثبت نشده است.']);
            }
            if ($locked->classification === Correspondence::CLASSIFICATION_CONFIDENTIAL) {
                $data['archive_visibility'] = 'restricted';
            }

            $record = $this->documents->createFromVersion($data, $primary, $user);
            foreach ($files as $file) {
                DB::table('archive_correspondence_files')->insert(['document_id' => $record->id, ...$file]);
            }
            $record->archiveReferences()->create([
                'target_type' => 'correspondence', 'target_id' => $locked->id, 'created_by' => $user->id,
            ]);
            $locked->archive_document_id = $record->id;
            $locked->save();

            return $record;
        });
    }
}
