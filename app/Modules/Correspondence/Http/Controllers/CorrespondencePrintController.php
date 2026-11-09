<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Http\Controllers;

use App\Core\Settings\Services\SystemSettingsService;
use App\Http\Controllers\Controller;
use App\Core\Departments\Models\PositionAssignment;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Services\CorrespondenceAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'creator:id,name',
            'createdDepartment:id,name,manager_id,manager_signature_path,manager_signature_active',
            'externalOrganization:id,name',
            'externalDepartment:id,name',
        ]);

        $snapshot = $correspondence->document_identity_snapshot ?? [];
        $organization = $snapshot['organization'] ?? (function () use ($settings): array {
            $values = $settings->get([
                'organization.name' => null,
                'organization.short_name' => null,
                'organization.address' => null,
                'organization.phone' => null,
                'organization.email' => null,
                'organization.website' => null,
            ]);

            return [
                'name' => $values['organization.name'],
                'short_name' => $values['organization.short_name'],
                'address' => $values['organization.address'],
                'phone' => $values['organization.phone'],
                'email' => $values['organization.email'],
                'website' => $values['organization.website'],
            ];
        })();

        $templateRaw = $settings->get(['correspondence.print.template' => null])['correspondence.print.template'];
        $templateElements = is_string($templateRaw) ? json_decode($templateRaw, true) : null;
        $templateElements = is_array($templateElements) ? array_values(array_map(static fn (array $item): array => [...$item, 'kind' => ($item['kind'] ?? 'text') === 'letterhead' ? 'text' : ($item['kind'] ?? 'text'), 'role' => $item['role'] ?? (in_array(($item['kind'] ?? null), ['stamp', 'signature'], true) ? 'issuer' : ((float) ($item['y'] ?? 0) < 25 ? 'repeat' : 'meta')), 'spacing' => $item['spacing'] ?? 0, 'font_family' => $item['font_family'] ?? 'default', 'page_format' => $item['page_format'] ?? 'number_of'], array_filter($templateElements, static fn (array $item): bool => ($item['kind'] ?? null) !== 'letterhead'))) : [];
        if (! collect($templateElements)->contains(static fn (array $item): bool => ($item['role'] ?? null) === 'body')) {
            $templateElements[] = ['id' => 'letter_body', 'kind' => 'text', 'role' => 'body', 'spacing' => 42, 'label' => 'متن اصلی نامه', 'content' => '', 'x' => 8, 'y' => 0, 'width' => 84, 'font_size' => 12, 'font_family' => 'default', 'page_format' => 'number_of', 'bold' => false, 'align' => 'right', 'visible' => true];
        }

        foreach ($templateElements as &$element) {
            if (($element['role'] ?? null) === 'body') {
                $element['spacing'] = 0;
            }
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
        ]);

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
            'letter' => [
                'id' => $correspondence->id,
                'type' => $correspondence->type,
                'subject' => $correspondence->subject,
                'attachment_pages' => $correspondence->attachment_pages,
                'body' => $correspondence->body,
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
            'stampDataUri' => $this->imageDataUri($stampPath),
            'letterheadDataUri' => $this->imageDataUri($letterheadPath),
            'signatureDataUri' => $this->imageDataUri($signaturePath),
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
