<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ArchiveAudienceService
{
    public function rules(bool $requireVisibility = false): array
    {
        return [
            'archive_visibility' => [$requireVisibility ? 'required' : 'sometimes', Rule::in(['department', 'organization', 'selected', 'restricted'])],
            'audience_departments' => ['exclude_unless:archive_visibility,selected', 'sometimes', 'array', 'max:100'],
            'audience_departments.*' => ['exclude_unless:archive_visibility,selected', 'required', 'integer', 'distinct', Rule::exists('departments', 'id')->where('is_active', true)],
            'audience_roles' => ['exclude_unless:archive_visibility,selected', 'sometimes', 'array', 'max:100'],
            'audience_roles.*' => ['exclude_unless:archive_visibility,selected', 'required', 'integer', 'distinct', Rule::exists('roles', 'id')->where('is_active', true)],
        ];
    }

    public function apply(Document $document, array $data): void
    {
        $visibility = $data['archive_visibility'];
        $departments = $visibility === 'selected' ? ($data['audience_departments'] ?? []) : [];
        $roles = $visibility === 'selected' ? ($data['audience_roles'] ?? []) : [];

        if ($visibility === 'selected' && $departments === [] && $roles === []) {
            throw ValidationException::withMessages(['archive_visibility' => 'حداقل یک واحد یا نقش را انتخاب کنید.']);
        }

        DB::transaction(function () use ($document, $visibility, $departments, $roles): void {
            $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $locked->update([
                'archive_visibility' => $visibility,
                'archive_restricted' => in_array($visibility, ['restricted', 'selected'], true),
            ]);
            DB::table('archive_audiences')->where('document_id', $document->id)->delete();

            $rows = [];
            foreach (['department' => $departments, 'role' => $roles] as $type => $ids) {
                foreach ($ids as $id) {
                    $rows[] = ['document_id' => $document->id, 'target_type' => $type, 'target_id' => $id];
                }
            }
            if ($rows !== []) {
                DB::table('archive_audiences')->insert($rows);
            }
        });
    }
}
