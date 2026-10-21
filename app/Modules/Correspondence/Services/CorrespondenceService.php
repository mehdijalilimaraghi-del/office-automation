<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Services;

use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
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

        return DB::transaction(function () use ($data, $creator, $type, $subject): Correspondence {
            $createdDepartmentId = $creator->departments()
                ->wherePivot('is_primary', true)
                ->value('departments.id');

            if ($createdDepartmentId === null && $creator->departments()->count() === 1) {
                $createdDepartmentId = $creator->departments()->value('departments.id');
            }

            return Correspondence::query()->create([
                'type' => $type,
                'classification' => $data['classification'] ?? Correspondence::CLASSIFICATION_ORDINARY,
                'subject' => $subject,
                'body' => $data['body'] ?? null,
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
                'reply_to_id' => $data['reply_to_id'] ?? null,
            ])->load(['creator', 'createdDepartment', 'recipientDepartment', 'replyTo']);
        });
    }
}
