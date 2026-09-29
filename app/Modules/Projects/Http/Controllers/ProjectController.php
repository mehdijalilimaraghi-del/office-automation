<?php

declare(strict_types=1);

namespace App\Modules\Projects\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\Projects\StoreProjectRequest;
use App\Http\Requests\Modules\Projects\UpdateProjectRequest;
use App\Models\User;
use App\Modules\Projects\Contracts\ProjectServiceContract;
use App\Modules\Projects\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectServiceContract $projectService,
    ) {
    }

    public function index(): Response
    {
        Gate::authorize('viewAny', Project::class);

        return Inertia::render('Projects/Index', [
            'projects' => $this->projectService->list(),
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

        return Inertia::render('Projects/Show', [
            'project' => $this->projectService->show($project),
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

    private function ensureActiveUser(User $user): void
    {
        abort_unless($user->isActive(), 403);
    }
}
