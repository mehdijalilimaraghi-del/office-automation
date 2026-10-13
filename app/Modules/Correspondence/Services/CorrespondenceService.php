<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Services;

use App\Core\Departments\Models\Department;
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

        if ($subject === '') {
            throw new InvalidArgumentException('موضوع مکاتبه الزامی است.');
        }

        return DB::transaction(function () use ($data, $creator, $type, $subject): Correspondence {
            return Correspondence::query()->create([
                'type' => $type,
                'subject' => $subject,
                'body' => $data['body'] ?? null,
                'status' => Correspondence::STATUS_DRAFT,
                'sender_name' => $data['sender_name'] ?? null,
                'recipient_name' => $data['recipient_name'] ?? null,
                'received_at' => $data['received_at'] ?? null,
                'sent_at' => $data['sent_at'] ?? null,
                'created_by' => $creator->id,
                'department_id' => $data['department_id'] ?? null,
                'reply_to_id' => $data['reply_to_id'] ?? null,
            ])->load(['creator', 'department', 'replyTo']);
        });
    }
}
