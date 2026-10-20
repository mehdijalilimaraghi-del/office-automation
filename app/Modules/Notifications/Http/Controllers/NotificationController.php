<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Notifications/Index', [
            'notifications' => $user->notifications()
                ->reorder()
                ->latest()
                ->limit(50)
                ->get()
                ->map(function ($notification) {
                    $type = $notification->data['type'] ?? 'general';

                    $title = match ($type) {
                        'task_assigned' => 'دستورکار جدید به شما تخصیص یافت',
                        'task_referred' => 'دستورکار به شما ارجاع شد',
                        'task_submitted' => 'دستورکار در انتظار تأیید شماست',
                        'task_returned' => 'دستورکار برای اصلاح بازگردانده شد',
                        'task_approved' => 'دستورکار تأیید و تکمیل شد',
                        'task_commented' => 'دیدگاه جدید برای دستورکار',
                        default => $notification->data['title'] ?? 'اعلان',
                    };

                    return [
                        'id' => $notification->id,
                        'type' => $type,
                        'title' => $title,
                        'message' => $notification->data['message'] ?? '',
                        'task_id' => $notification->data['task_id'] ?? null,
                        'url' => $notification->data['url'] ?? null,
                        'read_at' => $notification->read_at?->toIso8601String(),
                        'created_at' => $notification->created_at?->toIso8601String(),
                    ];
                }),
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    public function summary(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unreadCount' => $user->unreadNotifications()->count(),
            'notifications' => $user->notifications()
                ->reorder()
                ->latest()
                ->limit(7)
                ->get()
                ->map(function ($notification) {
                    $type = $notification->data['type'] ?? 'general';
                    $title = match ($type) {
                        'task_assigned' => 'دستورکار جدید به شما تخصیص یافت',
                        'task_referred' => 'دستورکار به شما ارجاع شد',
                        'task_submitted' => 'دستورکار در انتظار تأیید شماست',
                        'task_returned' => 'دستورکار برای اصلاح بازگردانده شد',
                        'task_approved' => 'دستورکار تأیید و تکمیل شد',
                        'task_commented' => 'دیدگاه جدید برای دستورکار',
                        default => $notification->data['title'] ?? 'اعلان',
                    };

                    return [
                        'id' => $notification->id,
                        'type' => $type,
                        'title' => $title,
                        'message' => $notification->data['message'] ?? '',
                        'url' => $notification->data['url'] ?? null,
                        'read_at' => $notification->read_at?->toIso8601String(),
                        'created_at' => $notification->created_at?->toIso8601String(),
                    ];
                }),
        ]);
    }

    public function markAsRead(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()
            ->whereKey($notification)
            ->firstOrFail()
            ->markAsRead();

        return back();
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->each->markAsRead();

        return back();
    }
}