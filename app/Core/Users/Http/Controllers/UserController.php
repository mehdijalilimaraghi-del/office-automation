<?php

namespace App\Core\Users\Http\Controllers;

use App\Core\Departments\Services\DepartmentService;
use App\Core\Roles\Services\RoleService;
use App\Core\Users\Contracts\UserServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\Users\StoreUserRequest;
use App\Http\Requests\Core\Users\UpdateUserRequest;
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
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Core/Users/Index', [
            'users' => $this->userService->list(),
            'departments' => $this->departmentService->list(),
            'roles' => $this->roleService->list(),
        ]);
    }

    public function store(
        StoreUserRequest $request
    ): RedirectResponse {
        $this->userService->create(
            $request->validated()
        );

        return back()->with(
            'success',
            'کاربر با موفقیت ایجاد شد.'
        );
    }

    public function update(
        UpdateUserRequest $request,
        User $user
    ): RedirectResponse {
        $this->userService->update(
            $user,
            $request->validated()
        );

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
}