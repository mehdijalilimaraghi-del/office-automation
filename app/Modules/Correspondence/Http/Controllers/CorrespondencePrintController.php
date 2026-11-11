<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Http\Controllers;

use App\Core\Settings\Services\SystemSettingsService;
use App\Core\Departments\Models\Department;
use App\Core\ExternalParties\Models\ExternalOrganization;
use App\Core\ExternalParties\Models\ExternalDepartment;
use App\Core\ExternalParties\Models\ExternalContact;
use App\Http\Controllers\Controller;
use App\Core\Departments\Models\PositionAssignment;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Services\CorrespondenceAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CorrespondencePrintController extends Controller
{
    public function __invoke(
        Request $request,
        Correspondence $correspondence,
        CorrespondenceAccessService $access,
        SystemSettingsService $settings,
    ): Response {
        abort_unless($access->canView($correspondence, $request->user()), 403);
        abort_unless($correspondence->status === Correspondence::STATUS_REGISTERED, 404);

        $correspondence->loadMissing([
            'documents.document.latestVersion',
            'creator:id,name',
            'createdDepartment:id,name,manager_id,manager_signature_path,manager_signature_active',
            'createdDepartment.manager:id,name',
            'externalOrganization:id,name',
            'externalDepartment:id,name',
        ]);

        return $this->render($correspondence, $settings, false);
    }

    public function preview(Request $request, SystemSettingsService $settings): Response
    {
        abort_unless($request->user()->hasPermission('correspondence.create'), 403);

        $payload = json_decode((string) $request->input('preview', ''), true);
        abort_unless(is_array($payload), 422, 'اطلاعات پیش‌نمایش معتبر نیست.');

        $allowedDepartmentIds = $request->user()->departments()
            ->where('departments.is_active', true)
            ->pluck('departments.id')
            ->merge($request->user()->managedDepartments()->where('is_active', true)->pluck('departments.id'))
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $data = Validator::make($payload, [
            'created_department_id' => ['nullable', 'integer', Rule::in($allowedDepartmentIds)],
            'subject' => ['nullable', 'string', 'max:255'],
            'composition_mode' => ['required', Rule::in(['editor', 'file', 'both'])],
            'body' => ['nullable', 'string', 'max:500000'],
            'sent_at' => ['nullable', 'date'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'external_organization_id' => ['nullable', 'integer', Rule::exists('external_organizations', 'id')->where('status', 'active')],
            'external_department_id' => ['nullable', 'integer', Rule::exists('external_departments', 'id')->where(fn ($query) => $query->where('status', 'active')->where('external_organization_id', $payload['external_organization_id'] ?? null))],
            'external_contact_id' => ['nullable', 'integer', Rule::exists('external_contacts', 'id')->where(fn ($query) => $query->where('status', 'active')->where('external_organization_id', $payload['external_organization_id'] ?? null)->where(function ($scope) use ($payload): void {
                $departmentId = $payload['external_department_id'] ?? null;
                $departmentId ? $scope->where('external_department_id', $departmentId) : $scope->whereNull('external_department_id');
            }))],
            'appendix_mode' => ['nullable', Rule::in(['none', 'editor', 'file', 'both'])],
            'appendix_body' => ['nullable', 'string', 'max:500000'],
            'appendix_file_count' => ['nullable', 'integer', 'min:0', 'max:10'],
        ])->validate();

        $department = ! empty($data['created_department_id'])
            ? Department::query()->with('manager:id,name')->findOrFail($data['created_department_id'])
            : null;
        $externalOrganization = ! empty($data['external_organization_id'])
            ? ExternalOrganization::query()->findOrFail($data['external_organization_id'])
            : null;
        $externalDepartment = ! empty($data['external_department_id'])
            ? ExternalDepartment::query()->findOrFail($data['external_department_id'])
            : null;
        $externalContact = ! empty($data['external_contact_id'])
            ? ExternalContact::query()->findOrFail($data['external_contact_id'])
            : null;

        $recipientAddressing = $externalContact === null ? null : [
            'organizational_title_prefix' => $externalContact->organizational_title_prefix,
            'position' => $externalContact->position,
            'personal_salutation' => $externalContact->personal_salutation,
            'professional_title' => $externalContact->professional_title,
            'name' => $externalContact->name,
            'organization_name' => $externalOrganization?->name,
            'department_name' => $externalDepartment?->name,
        ];

        $correspondence = new Correspondence([
            'type' => Correspondence::TYPE_OUTGOING,
            'subject' => $data['subject'] ?? '',
            'body' => ($data['composition_mode'] ?? 'editor') === 'file' ? '' : ($data['body'] ?? ''),
            'appendix_mode' => $data['appendix_mode'] ?? 'none',
            'appendix_body' => $data['appendix_body'] ?? null,
            'sent_at' => $data['sent_at'] ?? null,
            'recipient_name' => $data['recipient_name'] ?? $externalContact?->name,
            'created_by' => $request->user()->id,
            'created_department_id' => $department?->id,
            'external_organization_id' => $externalOrganization?->id,
            'external_department_id' => $externalDepartment?->id,
            'external_contact_id' => $externalContact?->id,
            'recipient_addressing_snapshot' => $recipientAddressing,
        ]);
        $correspondence->setRelation('creator', $request->user());
        $correspondence->setRelation('createdDepartment', $department);
        $correspondence->setRelation('externalOrganization', $externalOrganization);
        $correspondence->setRelation('externalDepartment', $externalDepartment);
        $correspondence->setRelation('documents', collect());
        $correspondence->setAttribute('preview_appendix_file_count', (int) ($data['appendix_file_count'] ?? 0));
        $correspondence->setAttribute(
            'preview_notice',
            in_array(($data['composition_mode'] ?? 'editor'), ['file', 'both'], true)
                ? 'فایل‌های بارگذاری‌شده در این پیش‌نمایش نمایش داده نمی‌شوند؛ این نما فقط قالب نامه و متن واردشده در ویرایشگر را نشان می‌دهد.'
                : null,
        );

        return $this->render($correspondence, $settings, true);
    }

    private function render(Correspondence $correspondence, SystemSettingsService $settings, bool $isPreview): Response
    {
        $snapshot = $correspondence->document_identity_snapshot ?? [];
        $organization = $snapshot['organization'] ?? (function () use ($settings): array {
            $values = $settings->get([
                'organization.name' => null,
                'organization.short_name' => null,
                'organization.address' => null,
                'organization.postal_code' => null,
                'organization.phone' => null,
                'organization.email' => null,
                'organization.website' => null,
            ]);

            return [
                'name' => $values['organization.name'],
                'short_name' => $values['organization.short_name'],
                'address' => $values['organization.address'],
                'postal_code' => $values['organization.postal_code'],
                'phone' => $values['organization.phone'],
                'email' => $values['organization.email'],
                'website' => $values['organization.website'],
            ];
        })();

        $templateRaw = $settings->get(['correspondence.print.template' => null])['correspondence.print.template'];
        $templateElements = is_string($templateRaw) ? json_decode($templateRaw, true) : null;
        $templateElements = is_array($templateElements) ? array_values(array_map(static fn (array $item): array => [...$item, 'kind' => ($item['kind'] ?? 'text') === 'letterhead' ? 'text' : ($item['kind'] ?? 'text'), 'role' => $item['role'] ?? (in_array(($item['kind'] ?? null), ['stamp', 'signature'], true) ? 'issuer' : ((float) ($item['y'] ?? 0) < 25 ? 'repeat' : 'meta')), 'spacing' => $item['spacing'] ?? 0, 'font_family' => $item['font_family'] ?? 'default', 'page_format' => $item['page_format'] ?? 'number_of'], array_filter($templateElements, static fn (array $item): bool => ($item['kind'] ?? null) !== 'letterhead'))) : [];
        if (! collect($templateElements)->contains(static fn (array $item): bool => ($item['role'] ?? null) === 'body')) {
            $templateElements[] = ['id' => 'letter_body', 'kind' => 'text', 'role' => 'body', 'spacing' => 12, 'label' => 'متن اصلی نامه', 'content' => '', 'x' => 8, 'y' => 0, 'width' => 84, 'font_size' => 12, 'font_family' => 'default', 'page_format' => 'number_of', 'bold' => false, 'align' => 'right', 'visible' => true];
        }

        $hasVisibleStampElement = collect($templateElements)->contains(static fn (array $item): bool => ($item['visible'] ?? true) && ($item['role'] ?? null) === 'issuer' && ($item['kind'] ?? null) === 'stamp');
        $hasVisibleSignatureElement = collect($templateElements)->contains(static fn (array $item): bool => ($item['visible'] ?? true) && ($item['role'] ?? null) === 'issuer' && ($item['kind'] ?? null) === 'signature');

        foreach ($templateElements as &$element) {
            if (($element['role'] ?? null) === 'meta' && ! str_contains((string) ($element['content'] ?? ''), '{{attachment_pages}}')) {
                $element['content'] = rtrim((string) ($element['content'] ?? ''))."\n{{attachment_pages}}";
            }
        }
        unset($element);

        $pageLayoutRaw = $settings->get(['correspondence.print.page_layout' => null])['correspondence.print.page_layout'];
        $pageLayout = is_string($pageLayoutRaw) ? json_decode($pageLayoutRaw, true) : null;
        $pageLayout = array_replace(['top' => 14, 'right' => 18, 'bottom' => 22, 'left' => 18, 'header_height' => 70, 'footer_height' => 25], is_array($pageLayout) ? $pageLayout : []);

        $stampPath = $snapshot['stamp_path'] ?? null;
        $letterheadPath = $snapshot['letterhead_path'] ?? null;
        $signaturePath = $snapshot['signature_path'] ?? null;

        // A registered letter can predate the upload/activation of its print assets.
        // Prefer its immutable snapshot when the file still exists, but fall back to
        // the currently configured asset when the snapshot has no usable file.
        $live = $settings->get([
            'organization.stamp.path' => null,
            'organization.stamp.active' => '0',
            'organization.letterhead.path' => null,
            'organization.postal_code' => null,
        ]);
        $organization['postal_code'] = filled($organization['postal_code'] ?? null) ? $organization['postal_code'] : $live['organization.postal_code'];

        if (! filled($stampPath) || ! Storage::disk('local')->exists($stampPath)) {
            $stampPath = $live['organization.stamp.active'] === '1'
                ? $live['organization.stamp.path']
                : null;
        }

        if (! filled($letterheadPath) || ! Storage::disk('local')->exists($letterheadPath)) {
            $letterheadPath = $live['organization.letterhead.path'];
        }

        $department = $correspondence->createdDepartment;
        $managerPosition = $department?->manager_id
            ? PositionAssignment::query()
                ->where('user_id', $department->manager_id)
                ->where('is_active', true)
                ->whereHas('position', fn ($query) => $query->where('department_id', $department->id)->where('is_active', true))
                ->with('position:id,name')
                ->orderByDesc('is_primary')
                ->first()?->position?->name
            : null;
        if (! filled($signaturePath) || ! Storage::disk('local')->exists($signaturePath)) {
            $signaturePath = $department?->manager_signature_active
                ? $department->manager_signature_path
                : null;
        }

        return Inertia::render('Correspondence/Print', [
            'isPreview' => $isPreview,
            'previewNotice' => $isPreview ? $correspondence->getAttribute('preview_notice') : null,
            'letter' => [
                'id' => $isPreview ? 0 : $correspondence->id,
                'type' => $correspondence->type,
                'subject' => $correspondence->subject,
                'attachment_pages' => $correspondence->attachment_pages,
                'body' => $correspondence->body,
                'appendix_mode' => $correspondence->appendix_mode ?? 'none',
                'appendix_body' => $correspondence->appendix_body,
                'appendix_file_count' => $isPreview ? (int) $correspondence->getAttribute('preview_appendix_file_count') : $correspondence->documents->where('purpose', 'correspondence_appendix')->filter(fn ($link) => $link->document?->latestVersion !== null)->count(),
                'letter_number' => $correspondence->letter_number,
                'registration_number' => $correspondence->registration_number,
                'registered_at' => $correspondence->registered_at?->toIso8601String(),
                'sent_at' => $correspondence->sent_at?->toIso8601String(),
                'recipient_name' => $correspondence->recipient_name,
                'recipient_addressing' => $correspondence->recipient_addressing_snapshot,
                'external_organization' => $correspondence->externalOrganization?->name,
                'external_department' => $correspondence->externalDepartment?->name,
            ],
            'organization' => $organization,
            'templateElements' => $templateElements,
            'pageLayout' => $pageLayout,
            'creatorName' => $snapshot['creator_name'] ?? $correspondence->creator?->name,
            'creatorUnitTitle' => $snapshot['creator_unit_title'] ?? ($correspondence->createdDepartment?->name ? 'مدیریت '.$correspondence->createdDepartment->name : null),
            'managerName' => $department?->manager?->name,
            'managerPosition' => $managerPosition,
            'managerTitle' => $managerPosition,
            'creatorDepartmentName' => $department?->name,
            'stampDataUri' => $hasVisibleStampElement ? $this->imageDataUri($stampPath) : null,
            'letterheadDataUri' => $this->imageDataUri($letterheadPath),
            'signatureDataUri' => $hasVisibleSignatureElement ? $this->imageDataUri($signaturePath) : null,
            'printOptions' => [
                'show_creator_identity' => $settings->get(['correspondence.print.show_creator_identity' => '1'])['correspondence.print.show_creator_identity'] === '1',
                'show_stamp' => $settings->get(['correspondence.print.show_stamp' => '1'])['correspondence.print.show_stamp'] === '1',
                'show_department_signature' => $settings->get(['correspondence.print.show_department_signature' => '1'])['correspondence.print.show_department_signature'] === '1',
                'letterhead_show_name' => $settings->get(['correspondence.print.letterhead.show_name' => '1'])['correspondence.print.letterhead.show_name'] === '1',
                'letterhead_show_address' => $settings->get(['correspondence.print.letterhead.show_address' => '1'])['correspondence.print.letterhead.show_address'] === '1',
                'letterhead_show_phone' => $settings->get(['correspondence.print.letterhead.show_phone' => '1'])['correspondence.print.letterhead.show_phone'] === '1',
                'letterhead_show_email' => $settings->get(['correspondence.print.letterhead.show_email' => '1'])['correspondence.print.letterhead.show_email'] === '1',
                'letterhead_show_website' => $settings->get(['correspondence.print.letterhead.show_website' => '1'])['correspondence.print.letterhead.show_website'] === '1',
                'font_family' => $settings->get(['correspondence.composition.font_family' => 'Vazirmatn'])['correspondence.composition.font_family'],
            ],
        ]);
    }

    private function imageDataUri(?string $path): ?string
    {
        if (! filled($path) || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('local')->mimeType($path);
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($path));
    }
}
