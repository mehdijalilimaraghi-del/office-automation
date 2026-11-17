<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Models\User;
use App\Modules\Documents\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArchiveCustodyService
{
    public function record(Document $document, User $actor, array $data): void
    {
        DB::transaction(function () use ($document, $actor, $data): void {
            $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->archive_kind === 'physical' && $locked->is_active, 404);

            $last = $locked->custodyEvents()->latest('id')->first();
            $action = $data['action'];
            $allowed = match ($last?->action) {
                'request' => ['approve', 'reject'],
                'approve' => ['checkout', 'reject'],
                'checkout' => ['return'],
                default => ['move', 'request'],
            };

            if (! in_array($action, $allowed, true)) {
                throw ValidationException::withMessages(['action' => 'این اقدام در وضعیت فعلی مدرک قابل اجرا نیست.']);
            }
            if ($action === 'approve' && (int) $last->actor_id === (int) $actor->id) {
                throw ValidationException::withMessages(['action' => 'درخواست باید توسط شخص دیگری تأیید شود.']);
            }

            $event = [
                ...$data,
                'actor_id' => $actor->id,
                'location' => $data['location'] ?? $locked->archive_location,
            ];
            if (in_array($action, ['approve', 'reject', 'checkout', 'return'], true)) {
                $event['recipient'] = $last->recipient;
                $event['due_on'] = $last->due_on;
            }

            $locked->custodyEvents()->create($event);
            if (in_array($action, ['move', 'return'], true)) {
                $locked->update(['archive_location' => $event['location']]);
            }
        });
    }
}
