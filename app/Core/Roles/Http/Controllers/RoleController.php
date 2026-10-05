<?php

namespace App\Core\Roles\Http\Controllers;

use App\Core\Roles\Contracts\RoleServiceInterface;
use App\Core\CustomFields\Services\CustomFieldService;
use App\Core\Roles\Models\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\Roles\StoreRoleRequest;
use App\Http\Requests\Core\Roles\UpdateRoleRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleServiceInterface $roleService,
        private readonly CustomFieldService $customFieldService,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Core/Roles/Index', [
            'roles' => $this->roleService->list(),
            'customFields' => $this->customFieldService->definitions(Role::class),
        ]);
    }

    public function store(
        StoreRoleRequest $request
    ): RedirectResponse {
        $this->roleService->create(
            $request->validated()
        );

        return back()->with(
            'success',
            'نقش با موفقیت ایجاد شد.'
        );
    }

    public function update(
        UpdateRoleRequest $request,
        Role $role
    ): RedirectResponse {
        $this->roleService->update(
            $role,
            $request->validated()
        );

        return back()->with(
            'success',
            'نقش با موفقیت ویرایش شد.'
        );
    }

    public function destroy(
        Role $role
    ): RedirectResponse {
        $this->roleService->delete($role);

        return back()->with(
            'success',
            'نقش با موفقیت حذف شد.'
        );
    }

    public function toggleStatus(
        Role $role
    ): RedirectResponse {
        $this->roleService->toggleStatus($role);

        return back()->with(
            'success',
            'وضعیت نقش با موفقیت تغییر کرد.'
        );
    }
}