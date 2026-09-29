<?php

declare(strict_types=1);

namespace App\Modules\Projects\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\Projects\ChangeProjectMemberRoleRequest;
use App\Http\Requests\Modules\Projects\StoreProjectMemberRequest;
use App\Modules\Projects\Contracts\ProjectMemberServiceContract;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ProjectMemberController extends Controller
{
    public function __construct(
        private readonly ProjectMemberServiceContract $projectMemberService,
    ) {
    }

    public function store(
        StoreProjectMemberRequest $request,
        Project $project
    ): RedirectResponse {
        Gate::authorize('manageMembers', $project);

        try {
            $this->projectMemberService->add(
                projectId: $project->id,
                userId: (int) $request->validated()['user_id'],
                projectRoleId: $request->validated()['project_role_id'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'user_id' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'عضو پروژه با موفقیت اضافه شد.'
        );
    }

    public function updateRole(
        ChangeProjectMemberRoleRequest $request,
        Project $project,
        ProjectMember $member
    ): RedirectResponse {
        Gate::authorize('manageMembers', $project);

        try {
            $this->projectMemberService->changeRole(
                member: $member,
                projectRoleId: $request->validated()['project_role_id'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'project_role_id' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'نقش عضو پروژه با موفقیت تغییر کرد.'
        );
    }

    public function destroy(
        Project $project,
        ProjectMember $member
    ): RedirectResponse {
        Gate::authorize('manageMembers', $project);

        try {
            $this->projectMemberService->remove($member);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'member' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'عضو پروژه با موفقیت حذف شد.'
        );
    }
}
