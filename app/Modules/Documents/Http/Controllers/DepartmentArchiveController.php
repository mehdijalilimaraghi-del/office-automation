<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers;

use App\Core\Departments\Models\Department;
use App\Models\User;
use App\Modules\Documents\Models\DepartmentArchiveSetting;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentArchiveController
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isActive(), 403);
        $user = $request->user();
        $departments = Department::query()->where('is_active', true)
            ->when(! $user->isSystemAdmin(), function ($query) use ($user): void {
                $query->where(function ($query) use ($user): void {
                    $query->where('manager_id', $user->id)
                        ->orWhere(function ($query) use ($user): void {
                            $query->whereHas('members', fn ($members) => $members->where('users.id', $user->id))
                                ->whereIn('id', DB::table('department_archive_settings')->where('archivist_id', $user->id)->select('department_id'));
                        });
                });
            })->orderBy('name')->get(['id', 'name', 'manager_id']);

        return Inertia::render('Archive/Departments', [
            'departments' => $departments,
            'centralDepartmentId' => app(\App\Core\Settings\Services\OrganizationDepartmentRoleService::class)->activeDepartmentId(\App\Core\Settings\Services\OrganizationDepartmentRoleService::CENTRAL_ARCHIVE),
        ]);
    }

    public function edit(Request $request, Department $department): Response
    {
        $this->authorize($request->user(), $department);
        $setting = DepartmentArchiveSetting::query()->where('department_id', $department->id)->first();

        return Inertia::render('Archive/DepartmentSettings', [
            'department' => $department->only(['id', 'name']),
            'centralMode' => (int) $department->id === app(\App\Core\Settings\Services\OrganizationDepartmentRoleService::class)->activeDepartmentId(\App\Core\Settings\Services\OrganizationDepartmentRoleService::CENTRAL_ARCHIVE),
            'settings' => [
                'archivist_id' => $setting?->archivist_id,
                'categories' => $setting?->categories ?? [],
                'locations' => $setting?->locations ?? [],
            ],
            'canAssign' => $request->user()->isSystemAdmin() || (int) $department->manager_id === (int) $request->user()->id,
            'members' => $department->members()->where('status', 'active')->orderBy('name')->get(['users.id', 'name']),
        ]);
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $this->authorize($request->user(), $department);
        $canAssign = $request->user()->isSystemAdmin() || (int) $department->manager_id === (int) $request->user()->id;
        $data = $request->validate([
            'archivist_id' => [$canAssign ? 'nullable' : 'prohibited', 'integer', Rule::exists('department_memberships', 'user_id')->where('department_id', $department->id)],
            'categories' => ['present', 'array', 'max:100'],
            'categories.*.name' => ['required', 'string', 'max:100', 'distinct'],
            'categories.*.approval_required' => ['required', 'boolean'],
            'locations' => ['present', 'array', 'max:200'],
            'locations.*.building' => ['required', 'string', 'max:60', 'not_regex:~[/\\\\]~'],
            'locations.*.room' => ['required', 'string', 'max:60', 'not_regex:~[/\\\\]~'],
            'locations.*.shelf' => ['required', 'string', 'max:60', 'not_regex:~[/\\\\]~'],
            'locations.*.folder' => ['nullable', 'string', 'max:60', 'not_regex:~[/\\\\]~'],
        ], [
            'locations.*.room.required' => 'برای هر ساختمان، اتاق تعریف کنید و محل را تا قفسه تکمیل کنید.',
            'locations.*.shelf.required' => 'برای هر اتاق، حداقل یک قفسه یا کمد تعریف کنید.',
            'locations.*.*.not_regex' => 'نام محل نباید شامل علامت / یا بک‌اسلش باشد.',
        ]);

        if (! empty($data['archivist_id']) && ! User::query()->whereKey($data['archivist_id'])->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['archivist_id' => 'مسئول بایگانی باید عضو فعال واحد باشد.']);
        }
        $paths = [];
        foreach ($data['locations'] as &$location) {
            $location = array_map(static fn ($value): string => trim((string) $value), array_intersect_key($location, array_flip(['building', 'room', 'shelf', 'folder'])));
            $path = implode(' / ', array_filter([
                $location['building'], $location['room'], $location['shelf'], $location['folder'] ?? '',
            ], static fn ($part): bool => $part !== ''));
            if (in_array($path, $paths, true)) {
                throw ValidationException::withMessages(['locations' => 'یک محل نگهداری بیش از یک بار تعریف شده است.']);
            }
            if (mb_strlen($path) > 255) {
                throw ValidationException::withMessages(['locations' => 'نشانی کامل محل نگهداری نباید بیش از ۲۵۵ نویسه باشد.']);
            }
            $paths[] = $path;
        }
        unset($location);

        DB::transaction(function () use ($department, $data, $paths): void {
            Department::query()->whereKey($department->id)->lockForUpdate()->firstOrFail();
            $used = \App\Modules\Documents\Models\Document::query()
                ->where('archive_department_id', $department->id)
                ->whereNotNull('archive_location')->where('archive_location', '<>', '')
                ->pluck('archive_location')->unique()->all();
            if (array_diff($used, $paths) !== []) {
                throw ValidationException::withMessages(['locations' => 'محل دارای سند قابل حذف نیست. ابتدا اسناد را از بخش جابه‌جایی به محل دیگری منتقل کنید.']);
            }
            DepartmentArchiveSetting::query()->updateOrCreate(['department_id' => $department->id], $data);
        });

        return back()->with('success', 'تنظیمات بایگانی واحد ذخیره شد.');
    }

    private function authorize(User $user, Department $department): void
    {
        abort_unless($user->isActive() && $department->is_active, 403);
        abort_unless($user->isSystemAdmin() || (int) $department->manager_id === (int) $user->id
            || ($department->members()->whereKey($user->id)->exists()
                && DepartmentArchiveSetting::query()->where('department_id', $department->id)->where('archivist_id', $user->id)->exists()), 403);
    }
}
