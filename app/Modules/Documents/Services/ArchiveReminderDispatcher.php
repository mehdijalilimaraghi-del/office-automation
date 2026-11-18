<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Core\Departments\Models\Department;
use App\Models\User;
use App\Modules\Documents\Models\DepartmentArchiveSetting;
use App\Modules\Documents\Models\Document;
use App\Modules\Notifications\Services\ReminderDeliveryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ArchiveReminderDispatcher
{
    public function dispatch(): int
    {
        $sent = 0;
        Document::query()->where('archive_kind', 'physical')->where('is_active', true)
            ->whereHas('latestCustodyEvent', fn ($query) => $query->where('action', 'checkout')->where('due_on', '<=', today()->addDay()->toDateString()))
            ->orderBy('id')->chunkById(100, function ($documents) use (&$sent): void {
                foreach ($documents as $document) {
                    $sent += DB::transaction(function () use ($document): int {
                        $record = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
                        $event = $record->custodyEvents()->latest('id')->first();
                        if ($event?->action !== 'checkout' || $event->due_on === null || ! $record->is_active) {
                            return 0;
                        }
                        $days = (int) today()->diffInDays(Carbon::parse($event->due_on)->startOfDay(), false);
                        if ($days > 1) {
                            return 0;
                        }
                        $department = Department::query()->whereKey($record->archive_department_id)->where('is_active', true)->first();
                        $setting = DepartmentArchiveSetting::query()->where('department_id', $record->archive_department_id)->first();
                        $responsible = null;
                        foreach (array_filter([$setting?->archivist_id, $department?->manager_id, $record->created_by]) as $candidate) {
                            if (User::query()->whereKey($candidate)->where('status', 'active')->exists()) {
                                $responsible = $candidate;
                                break;
                            }
                        }
                        $recipients = [$responsible, $event->recipient_user_id];
                        if ($days <= -3) {
                            $recipients[] = $department?->manager_id;
                        }
                        $title = match (true) {
                            $days === 1 => 'یادآوری بازگشت مدرک در روز آینده',
                            $days === 0 => 'موعد بازگشت مدرک امروز است',
                            default => 'تأخیر در بازگشت مدرک',
                        };
                        $count = 0;
                        foreach (User::query()->whereIn('id', array_unique(array_filter($recipients)))->where('status', 'active')->orderBy('id')->get() as $user) {
                            $canView = Gate::forUser($user)->allows('view', $record);
                            $count += (int) app(ReminderDeliveryService::class)->sendOnce(
                                $user, 'archive:'.$event->id.':'.today()->toDateString(),
                                ['type' => 'archive_return_reminder', 'title' => $title,
                                    'message' => $canView ? $record->title : 'برای پیگیری بازگشت مدرک با بایگانی هماهنگ کنید.',
                                    'url' => $canView ? route('archive.show', $record) : null],
                            );
                        }

                        return $count;
                    });
                }
            });

        return $sent;
    }
}
