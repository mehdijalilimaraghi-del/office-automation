<?php

declare(strict_types=1);

namespace App\Modules\Projects\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\Projects\StoreProjectRoleRequest;
use App\Http\Requests\Modules\Projects\UpdateProjectRoleRequest;
use App\Modules\Projects\Contracts\ProjectRoleServiceContract;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ProjectRoleController extends Controller
{
    public function __construct(
        private readonly ProjectRoleServiceContract $projectRoleService,
    ) {
    }

    public function store(
        StoreProjectRoleRequest $request,
        Project $project
    ): RedirectResponse {
        Gate::authorize('manageRoles', $project);

        try {
            $this->projectRoleService->create(
                projectId: $project->id,
                data: $request->validated(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'code' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'نقش پروژه با موفقیت ایجاد شد.'
        );
    }

    public function update(
        UpdateProjectRoleRequest $request,
        Project $project,
        ProjectRole $role
    ): RedirectResponse {
        Gate::authorize('manageRoles', $project);

        try {
            $this->projectRoleService->update(
                role: $role,
                data: $request->validated(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'code' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'نقش پروژه با موفقیت ویرایش شد.'
        );
    }

    public function toggleStatus(
        Project $project,
        ProjectRole $role
    ): RedirectResponse {
        Gate::authorize('manageRoles', $project);

        try {
            $this->projectRoleService->toggleStatus($role);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'role' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'وضعیت نقش پروژه با موفقیت تغییر کرد.'
        );
    }

    public function destroy(
        Project $project,
        ProjectRole $role
    ): RedirectResponse {
        Gate::authorize('manageRoles', $project);

        try {
            $this->projectRoleService->delete($role);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'role' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'نقش پروژه با موفقیت حذف شد.'
        );
    }
}
