<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Services;

use App\Models\User;
use App\Core\Departments\Models\Department;
use App\Core\ExternalParties\Models\ExternalOrganization;
use App\Core\ExternalParties\Models\ExternalDepartment;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Services\CorrespondenceNumberingService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CorrespondenceService
{
    public function createDraft(array $data, User $creator): Correspondence
    {
        $type = $data['type'] ?? null;
        $subject = trim((string) ($data['subject'] ?? ''));

        if (! in_array($type, Correspondence::allowedTypes(), true)) {
            throw new InvalidArgumentException('نوع مکاتبه نامعتبر است.');
        }

        if (! app(CorrespondenceAccessService::class)->canCreateType((string) $type, $creator)) {
            throw new InvalidArgumentException('ایجاد نامه وارده فقط برای کاربران واحد دبیرخانه مجاز است.');
        }

        if ($subject === '') {
            throw new InvalidArgumentException('موضوع مکاتبه الزامی است.');
        }
        if ($type === Correspondence::TYPE_INCOMING && blank($data['letter_number'] ?? null)) {
            throw new InvalidArgumentException('شماره نامه وارده باید عیناً از روی نامه اصلی وارد شود.');
        }

        $externalOrganizationId = isset($data['external_organization_id']) && $data['external_organization_id'] !== ''
            ? (int) $data['external_organization_id'] : null;
        $externalDepartmentId = isset($data['external_department_id']) && $data['external_department_id'] !== ''
            ? (int) $data['external_department_id'] : null;

        if ($type === Correspondence::TYPE_INTERNAL && ($externalOrganizationId !== null || $externalDepartmentId !== null)) {
            throw new InvalidArgumentException('برای مکاتبه داخلی نباید طرف برون‌سازمانی انتخاب شود.');
        }
        if ($externalDepartmentId !== null && $externalOrganizationId === null) {
            throw new InvalidArgumentException('برای انتخاب واحد بیرونی، ابتدا سازمان بیرونی را انتخاب کنید.');
        }
        if ($externalOrganizationId !== null && ! ExternalOrganization::query()->whereKey($externalOrganizationId)->where('status', 'active')->exists()) {
            throw new InvalidArgumentException('سازمان بیرونی انتخاب‌شده فعال نیست یا وجود ندارد.');
        }
        if ($externalDepartmentId !== null && ! ExternalDepartment::query()->whereKey($externalDepartmentId)->where('external_organization_id', $externalOrganizationId)->where('status', 'active')->exists()) {
            throw new InvalidArgumentException('واحد بیرونی انتخاب‌شده متعلق به سازمان انتخاب‌شده نیست یا فعال نیست.');
        }

        return DB::transaction(function () use ($data, $creator, $type, $subject, $externalOrganizationId, $externalDepartmentId): Correspondence {
            $createdDepartmentId = $data['created_department_id'] ?? null;
            $allowedDepartmentIds = $creator->departments()
                ->where('departments.is_active', true)
                ->pluck('departments.id')
                ->merge($creator->managedDepartments()->where('is_active', true)->pluck('id'))
                ->unique()
                ->values();

            if ($createdDepartmentId !== null && ! $allowedDepartmentIds->contains((int) $createdDepartmentId)) {
                throw new InvalidArgumentException('واحد ایجادکننده باید یکی از واحدهای فعال محل عضویت یا مدیریت شما باشد.');
            }

            if ($createdDepartmentId === null) {
                $createdDepartmentId = $creator->departments()
                    ->wherePivot('is_primary', true)
                    ->where('departments.is_active', true)
                    ->value('departments.id');
            }

            if ($createdDepartmentId === null && $allowedDepartmentIds->count() === 1) {
                $createdDepartmentId = $allowedDepartmentIds->first();
            }

            $requiresApproval = app(CorrespondenceCompositionSettings::class)->requiresApproval((string) $type);
            $initialState = 'awaiting_registration';
            $currentUserId = null;

            if ($requiresApproval) {
                $managerId = $createdDepartmentId === null
                    ? null
                    : Department::query()->whereKey($createdDepartmentId)->value('manager_id');
                if ($managerId === null || ! User::query()->whereKey($managerId)->where('status', 'active')->exists()) {
                    throw new InvalidArgumentException('برای واحد ایجادکننده، مدیر فعال جهت تأیید نامه در تنظیمات واحدها مشخص نشده است.');
                }

                $initialState = 'awaiting_manager_approval';
                $currentUserId = (int) $managerId;
            }

            $attachmentPages = array_key_exists('attachment_pages', $data) && $data['attachment_pages'] !== null && $data['attachment_pages'] !== ''
                ? (int) $data['attachment_pages']
                : null;

            $correspondence = Correspondence::query()->create([
                'workflow_state' => $initialState,
                'current_department_id' => $createdDepartmentId,
                'current_user_id' => $currentUserId,
                'type' => $type,
                'classification' => $data['classification'] ?? Correspondence::CLASSIFICATION_ORDINARY,
                'subject' => $subject,
                'letter_number' => $type === Correspondence::TYPE_INCOMING ? trim((string) ($data['letter_number'] ?? '')) : null,
                'body' => $data['body'] ?? null,
                'attachment_pages' => $attachmentPages,
                'appendix_mode' => $data['appendix_mode'] ?? 'editor',
                'appendix_body' => $data['appendix_body'] ?? null,
                'status' => Correspondence::STATUS_DRAFT,
                'sender_name' => $type === Correspondence::TYPE_INTERNAL
                    ? $creator->name
                    : ($data['sender_name'] ?? null),
                'recipient_name' => $data['recipient_name'] ?? null,
                'received_at' => $data['received_at'] ?? null,
                'sent_at' => $data['sent_at'] ?? null,
                'created_by' => $creator->id,
                'created_department_id' => $createdDepartmentId,
                'recipient_department_id' => $data['recipient_department_id'] ?? null,
                'external_organization_id' => $externalOrganizationId,
                'external_department_id' => $externalDepartmentId,
                'reply_to_id' => $data['reply_to_id'] ?? null,
            ]);

            if ($type !== Correspondence::TYPE_INCOMING) $correspondence->forceFill(['letter_number' => app(CorrespondenceNumberingService::class)->nextNumber($correspondence)])->save();

            return $correspondence->load(['creator', 'createdDepartment', 'recipientDepartment', 'replyTo']);
        });
    }
}
