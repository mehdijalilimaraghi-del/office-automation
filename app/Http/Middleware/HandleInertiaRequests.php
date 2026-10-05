<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->load('profile'),
                'abilities' => [
                    'tasks_view' => $request->user()?->hasPermission('tasks.view') ?? false,
                    'projects_view' => $request->user()?->hasPermission('projects.view') ?? false,
                    'external_parties_view' => $request->user()?->hasPermission('external_parties.view') ?? false,
                    'notification_view' => $request->user()?->hasPermission('notifications.view') ?? false,
                    'settings_manage' => $request->user()?->hasPermission('settings.manage') ?? false,
                ],
            ],
        ];
    }
}
