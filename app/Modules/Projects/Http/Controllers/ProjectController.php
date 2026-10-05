<?php

declare(strict_types=1);

namespace App\Modules\Projects\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Core\CustomFields\Services\CustomFieldService;
use App\Http\Requests\Modules\Projects\StoreProjectRequest;
use App\Http\Requests\Modules\Projects\UpdateProjectRequest;
use App\Core\Departments\Contracts\DepartmentServiceInterface;
use App\Core\Users\Contracts\UserServiceInterface;
use App\Modules\Projects\Contracts\ProjectMemberServiceContract;
use App\Modules\Projects\Contracts\ProjectServiceContract;
use App\Modules\Projects\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectServiceContract $projectService,
        private readonly ProjectMemberServiceContract $projectMemberService,
        private readonly UserServiceInterface $userService,
        private readonly DepartmentServiceInterface $departmentService,
        private readonly CustomFieldService $customFieldService,
    ) {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Project::class);

        return Inertia::render('Projects/Index', [
            'projects' => $this->projectService->list(),
            'canCreate' => Gate::allows('create', Project::class),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Project::class);

        return Inertia::render('Projects/Create', [
            'managers' => $this->userService->activeList(),
            'departments' => $this->departmentService->list()->where('is_active', true)->values(),
            'customFields' => $this->customFieldService->definitions(Project::class),
        ]);
    }

    public function edit(Project $project): Response
    {
        Gate::authorize('update', $project);

        return Inertia::render('Projects/Edit', [
            'project' => $this->projectService->show($project),
            'departments' => $this->departmentService->list()->where('is_active', true)->values(),
            'customFields' => $this->customFieldService->definitions(Project::class),
            'customFieldValues' => $this->customFieldService->values($this->projectService->show($project)),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        Gate::authorize('create', Project::class);

        try {
            $this->projectService->create(
                data: $request->validated(),
                creator: $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'manager_id' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'پروژه با موفقیت ایجاد شد.'
        );
    }

    public function show(Project $project): Response
    {
        Gate::authorize('view', $project);

        $canManageMembers = Gate::allows('manageMembers', $project);

        return Inertia::render('Projects/Show', [
            'project' => $this->projectService->show($project),
            'canUpdate' => Gate::allows('update', $project),
            'canManageMembers' => $canManageMembers,
            'canManageRoles' => Gate::allows('manageRoles', $project),
            'memberOptions' => $canManageMembers
                ? $this->projectMemberService->availableUsers($project)
                : [],
        ]);
    }

    public function update(
        UpdateProjectRequest $request,
        Project $project
    ): RedirectResponse {
        Gate::authorize('update', $project);

        try {
            $this->projectService->update(
                project: $project,
                data: $request->validated(),
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'project' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'پروژه با موفقیت ویرایش شد.'
        );
    }

    public function destroy(Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $this->projectService->delete($project);

        return back()->with(
            'success',
            'پروژه با موفقیت حذف شد.'
        );
    }

}
