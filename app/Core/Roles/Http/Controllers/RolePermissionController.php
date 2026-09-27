<?php

namespace App\Core\Roles\Http\Controllers;

use App\Core\Permissions\Services\PermissionService;
use App\Core\Roles\Contracts\RoleServiceInterface;
use App\Core\Roles\Models\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\Roles\SyncRolePermissionsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RolePermissionController extends Controller
{
    public function __construct(
        private readonly RoleServiceInterface $roleService,
        private readonly PermissionService $permissionService,
    ) {
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('Core/Roles/Permissions', [
            'role' => $role->load('permissions'),
            'permissions' => $this->permissionService->list(),
        ]);
    }

    public function update(
        SyncRolePermissionsRequest $request,
        Role $role
    ): RedirectResponse {
        $this->roleService->syncPermissions(
            $role,
            $request->validated('permission_ids')
        );

        return back()->with(
            'success',
            'مجوزهای نقش با موفقیت به‌روزرسانی شد.'
        );
    }
}