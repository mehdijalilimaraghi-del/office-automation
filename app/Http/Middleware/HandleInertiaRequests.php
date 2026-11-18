<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Inertia;
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
                    'correspondence_view' => $request->user()?->hasPermission('correspondence.view') ?? false,
                    'correspondence_create' => $request->user()?->hasPermission('correspondence.create') ?? false,
                    'archive_units_manage' => $request->user()?->isActive() && ($request->user()->isSystemAdmin() || $request->user()->managedDepartments()->where('is_active', true)->exists() || \App\Modules\Documents\Models\DepartmentArchiveSetting::query()->where('archivist_id', $request->user()->id)->whereIn('department_id', $request->user()->departments()->where('departments.is_active', true)->select('departments.id'))->exists()),
                    'documents_view' => $request->user() !== null && app(\App\Modules\Documents\Services\ArchiveAccessService::class)->canEnter($request->user()),
                    'projects_view' => $request->user()?->hasPermission('projects.view') ?? false,
                    'users_view' => $request->user()?->hasPermission('users.view') ?? false,
                    'departments_view' => $request->user()?->hasPermission('departments.view') ?? false,
                    'roles_view' => $request->user()?->hasPermission('roles.view') ?? false,
                    'external_parties_view' => $request->user()?->hasPermission('external_parties.view') ?? false,
                    'notification_view' => $request->user()?->hasPermission('notifications.view') ?? false,
                    'settings_manage' => $request->user()?->hasPermission('settings.manage') ?? false,
                    'departments_create' => $request->user()?->hasPermission('departments.create') ?? false,
                    'departments_edit' => $request->user()?->hasPermission('departments.edit') ?? false,
                    'departments_delete' => $request->user()?->hasPermission('departments.delete') ?? false,
                    'departments_toggle_status' => $request->user()?->hasPermission('departments.toggle_status') ?? false,
                    'departments_manage_positions' => $request->user()?->hasPermission('departments.manage_positions') ?? false,
                    'users_manage_departments' => $request->user()?->hasPermission('users.manage_departments') ?? false,
                    'users_manage_roles' => $request->user()?->hasPermission('users.manage_roles') ?? false,
                    'custom_fields_manage' => $request->user()?->hasPermission('custom_fields.manage') ?? false,
                ],
            ],
        ];
    }

    protected function reflash(Request $request): void
    {
        if ($request->user() && $request->hasSession() && ! $request->isMethodSafe()) {
            $session = $request->session();
            if (! $session->hasAny(['errors', 'error', 'warning'])) {
                $message = $session->get('success');
                $action = $request->route()?->getActionMethod() ?? '';
                if (! filled($message) && preg_match('/^(store|update|save)([A-Z]|$)/', $action)) {
                    $message = 'تغییرات ذخیره شد.';
                }
                if (is_string($message) && filled($message)) {
                    Inertia::flash('saveSuccess', $message);
                }
            }
        }

        parent::reflash($request);
    }
}
