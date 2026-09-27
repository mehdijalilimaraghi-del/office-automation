<?php

namespace App\Core\Permissions\Http\Controllers;

use App\Core\Permissions\Contracts\PermissionServiceInterface;
use App\Core\Permissions\Models\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\Permissions\StorePermissionRequest;
use App\Http\Requests\Core\Permissions\UpdatePermissionRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function __construct(
        private readonly PermissionServiceInterface $permissionService,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Core/Permissions/Index', [
            'permissions' => $this->permissionService->list(),
        ]);
    }

    public function store(
        StorePermissionRequest $request
    ): RedirectResponse {
        $this->permissionService->create(
            $request->validated()
        );

        return back()->with(
            'success',
            'مجوز با موفقیت ایجاد شد.'
        );
    }

    public function update(
        UpdatePermissionRequest $request,
        Permission $permission
    ): RedirectResponse {
        $this->permissionService->update(
            $permission,
            $request->validated()
        );

        return back()->with(
            'success',
            'مجوز با موفقیت ویرایش شد.'
        );
    }

    public function destroy(
        Permission $permission
    ): RedirectResponse {
        $this->permissionService->delete($permission);

        return back()->with(
            'success',
            'مجوز با موفقیت حذف شد.'
        );
    }

    public function toggleStatus(
        Permission $permission
    ): RedirectResponse {
        $this->permissionService->toggleStatus($permission);

        return back()->with(
            'success',
            'وضعیت مجوز با موفقیت تغییر کرد.'
        );
    }
}