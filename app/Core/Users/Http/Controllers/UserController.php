<?php

namespace App\Core\Users\Http\Controllers;

use App\Core\Departments\Services\DepartmentService;
use App\Core\Roles\Services\RoleService;
use App\Core\Users\Actions\InviteUserAction;
use App\Core\Users\Contracts\UserServiceInterface;
use App\Core\CustomFields\Models\CustomField;
use App\Core\CustomFields\Services\CustomFieldService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\Users\StoreUserRequest;
use App\Http\Requests\Core\Users\UpdateUserRequest;
use App\Http\Requests\Core\Users\SyncUserDepartmentsRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserServiceInterface $userService,
        private readonly DepartmentService $departmentService,
        private readonly RoleService $roleService,
        private readonly InviteUserAction $inviteUserAction,
        private readonly CustomFieldService $customFieldService,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Core/Users/Index', [
            'users' => $this->userService->list(),
            'departments' => $this->departmentService->list(),
            'roles' => $this->roleService->list(),
            'customFields' => CustomField::query()->where('entity_type', User::class)->where('is_active', true)->orderBy('sort_order')->orderBy('label')->get(),
            'customFieldValues' => $this->userService->list()->mapWithKeys(fn (User $user) => [$user->id => $this->customFieldService->values($user)]),
        ]);
    }

    public function store(
        StoreUserRequest $request
    ): RedirectResponse {
        $data = $request->validated();

        if (array_key_exists('role_ids', $data)) {
            $this->ensureRoleManagementAllowed($request->user(), $data['role_ids']);
        }

        $user = $this->userService->create($data);

        if ($user->isInactive()) {
            $this->inviteUserAction->execute($user);
        }

        return back()->with(
            'success',
            'کاربر با موفقیت ایجاد شد.'
        );
    }

    public function invite(User $user): RedirectResponse
    {
        try {
            $this->inviteUserAction->execute($user);
        } catch (ValidationException $exception) {
            return back()->with('error', $exception->validator->errors()->first('user'));
        }

        return back()->with('success', 'دعوت‌نامه کاربر ارسال شد.');
    }

    public function syncDepartments(
        SyncUserDepartmentsRequest $request,
        User $user
    ): RedirectResponse {
        $this->userService->syncDepartments(
            $user,
            $request->validated('department_ids', []),
            $request->validated('primary_department_id')
        );

        return back()->with(
            'success',
            'عضویت کاربر در واحدهای سازمانی با موفقیت به‌روزرسانی شد.'
        );
    }

    public function update(
        UpdateUserRequest $request,
        User $user
    ): RedirectResponse {
        $data = $request->validated();

        if (array_key_exists('role_ids', $data)) {
            $this->ensureRoleManagementAllowed($request->user(), $data['role_ids']);
        }

        $this->userService->update($user, $data);

        return back()->with(
            'success',
            'کاربر با موفقیت ویرایش شد.'
        );
    }

    public function destroy(
        User $user
    ): RedirectResponse {
        try {
            $this->userService->delete($user);
        } catch (ValidationException $exception) {
            return back()->with(
                'error',
                $exception->validator->errors()->first('user')
            );
        }

        return back()->with(
            'success',
            'کاربر با موفقیت حذف شد.'
        );
    }

    public function toggleStatus(
        User $user
    ): RedirectResponse {
        try {
            $this->userService->toggleStatus($user);
        } catch (ValidationException $exception) {
            return back()->with(
                'error',
                $exception->validator->errors()->first('user')
            );
        }

        return back()->with(
            'success',
            'وضعیت کاربر با موفقیت تغییر کرد.'
        );
    }

    private function ensureRoleManagementAllowed(User $actor, array $roleIds): void
    {
        if ($roleIds === [] || $actor->hasPermission('users.manage_roles')) {
            return;
        }

        abort(403, 'برای تخصیص یا تغییر نقش کاربران، مجوز مدیریت نقش‌های کاربر لازم است.');
    }
}