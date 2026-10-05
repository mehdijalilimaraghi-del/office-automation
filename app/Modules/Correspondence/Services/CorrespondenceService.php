<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Services;

use App\Core\Audit\Contracts\AuditServiceInterface;
use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Models\CorrespondenceReferral;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CorrespondenceService
{
    public function __construct(private readonly AuditServiceInterface $audit) {}

    public function list(array $filters = []): Collection
    {
        $query = Correspondence::query()->with([
            'creator',
            'senderOrganization',
            'senderContact',
            'recipientOrganization',
            'recipientContact',
        ]);

        if (! empty($filters['type'])) $query->where('type', $filters['type']);
        if (! empty($filters['status'])) $query->where('status', $filters['status']);
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search): void {
                $q->where('registration_number', 'like', '%'.$search.'%')
                    ->orWhere('subject', 'like', '%'.$search.'%');
            });
        }

        return $query->latest('id')->limit(100)->get();
    }

    public function create(array $data, User $creator): Correspondence
    {
        return DB::transaction(function () use ($data, $creator): Correspondence {
            $correspondence = Correspondence::query()->create([
                'registration_number' => $this->nextRegistrationNumber(),
                'type' => $data['type'],
                'status' => $data['status'] ?? 'draft',
                'registered_at' => ($data['status'] ?? 'draft') === 'registered' ? ($data['registered_at'] ?? now()->toDateString()) : null,
                'subject' => $data['subject'],
                'body' => $data['body'] ?? null,
                'sender_organization_id' => $data['sender_organization_id'] ?? null,
                'sender_department_id' => $data['sender_department_id'] ?? null,
                'sender_contact_id' => $data['sender_contact_id'] ?? null,
                'recipient_organization_id' => $data['recipient_organization_id'] ?? null,
                'recipient_department_id' => $data['recipient_department_id'] ?? null,
                'recipient_contact_id' => $data['recipient_contact_id'] ?? null,
                'created_by' => $creator->id,
            ]);

            $this->audit->record('correspondence.created', $correspondence, null, $correspondence->toArray());

            return $correspondence->load(['creator']);
        });
    }

    public function register(Correspondence $correspondence): Correspondence
    {
        if ($correspondence->status !== 'draft') {
            throw new \DomainException('Only draft correspondence can be registered.');
        }

        $correspondence->forceFill([
            'status' => 'registered',
            'registered_at' => now()->toDateString(),
        ])->save();

        $this->audit->record('correspondence.registered', $correspondence, ['status' => 'draft'], ['status' => 'registered']);

        return $correspondence->refresh();
    }

    public function refer(Correspondence $correspondence, User $toUser, User $actor, ?string $instruction = null): CorrespondenceReferral
    {
        if ($correspondence->status !== 'registered') {
            throw new \DomainException('Only registered correspondence can be referred.');
        }

        $referral = CorrespondenceReferral::query()->create([
            'correspondence_id' => $correspondence->id,
            'from_user_id' => $actor->id,
            'to_user_id' => $toUser->id,
            'referred_by' => $actor->id,
            'instruction' => $instruction,
            'referred_at' => now(),
        ]);

        $this->audit->record('correspondence.referred', $correspondence, null, [
            'to_user_id' => $toUser->id,
            'instruction' => $instruction,
        ]);

        return $referral->load(['toUser', 'fromUser']);
    }

    private function nextRegistrationNumber(): string
    {
        $last = Correspondence::query()->lockForUpdate()->orderByDesc('id')->value('registration_number');
        $number = $last !== null && ctype_digit($last) ? ((int) $last) + 1 : 10001;

        return (string) $number;
    }
}