<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Http\Controllers;

use App\Core\Settings\Services\SystemSettingsService;
use App\Http\Controllers\Controller;
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
            'createdDepartment:id,name',
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
        $templateElements = is_array($templateElements) ? $templateElements : [];

        $stampPath = $snapshot['stamp_path'] ?? null;
        $letterheadPath = $snapshot['letterhead_path'] ?? null;
        $signaturePath = $snapshot['signature_path'] ?? null;

        if ($correspondence->document_identity_snapshot === null) {
            $live = $settings->get([
                'organization.stamp.path' => null,
                'organization.stamp.active' => '0',
                'organization.letterhead.path' => null,
            ]);
            $letterheadPath = $live['organization.letterhead.path'];
            if ($live['organization.stamp.active'] === '1') {
                $stampPath = $live['organization.stamp.path'];
            }

            $department = $correspondence->createdDepartment;
            if ($department !== null && $department->manager_signature_active && $correspondence->workflow_state === 'approved_for_registration') {
                $signaturePath = $department->manager_signature_path;
            }
        }

        return Inertia::render('Correspondence/Print', [
            'letter' => [
                'id' => $correspondence->id,
                'type' => $correspondence->type,
                'subject' => $correspondence->subject,
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
            'creatorName' => $snapshot['creator_name'] ?? $correspondence->creator?->name,
            'creatorUnitTitle' => $snapshot['creator_unit_title'] ?? ($correspondence->createdDepartment?->name ? 'مدیریت '.$correspondence->createdDepartment->name : null),
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
