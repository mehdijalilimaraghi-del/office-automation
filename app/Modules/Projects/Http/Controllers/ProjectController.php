<?php

declare(strict_types=1);

namespace App\Modules\Projects\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Core\CustomFields\Services\CustomFieldService;
use App\Http\Requests\Modules\Projects\StoreProjectRequest;
use App\Http\Requests\Modules\Projects\UpdateProjectRequest;
use App\Core\Departments\Contracts\DepartmentServiceInterface;
use App\Core\Departments\Models\Department;
use App\Core\Settings\Services\OrganizationDepartmentRoleService;
use App\Core\Permissions\Enums\PermissionScope;
use App\Core\Users\Contracts\UserServiceInterface;
use App\Modules\Projects\Contracts\ProjectMemberServiceContract;
use App\Modules\Projects\Contracts\ProjectServiceContract;
use App\Modules\Projects\Models\Project;
use App\Modules\Tasks\Models\Task;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Services\DocumentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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
        private readonly DocumentService $documentService,
    ) {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Project::class);

        return Inertia::render('Projects/Index', [
            'projects' => $this->projectService->list($request->user()),
            'canCreate' => Gate::allows('create', Project::class),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Project::class);

        $allDepartments = collect($this->departmentService->list());
        $departmentsByParent = $allDepartments->groupBy('parent_id');
        $departmentOptions = collect();

        $appendDepartmentOptions = function ($parentId, int $depth = 0) use (
            &$appendDepartmentOptions,
            $departmentsByParent,
            $departmentOptions
        ): void {
            foreach ($departmentsByParent->get($parentId, collect()) as $department) {
                if ($department->is_active && $department->manager_id !== null) {
                    $department->setAttribute(
                        'tree_label',
                        str_repeat('　　', $depth) . ($depth > 0 ? '└─ ' : '') . $department->name
                    );
                    $departmentOptions->push($department);
                }

                $appendDepartmentOptions($department->id, $depth + 1);
            }
        };

        $appendDepartmentOptions(null);

        $projectManagementDepartmentId = app(OrganizationDepartmentRoleService::class)
            ->activeDepartmentId(OrganizationDepartmentRoleService::PROJECT_MANAGEMENT);

        return Inertia::render('Projects/Create', [
            'managers' => \App\Models\User::query()
                ->where('status', 'active')
                ->where(function ($query) use ($projectManagementDepartmentId): void {
                    if ($projectManagementDepartmentId !== null) {
                        $query->whereHas('departments', fn ($departmentQuery) => $departmentQuery
                            ->whereKey($projectManagementDepartmentId)
                            ->where('departments.is_active', true))
                            ->orWhereHas('managedDepartments', fn ($departmentQuery) => $departmentQuery
                                ->whereKey($projectManagementDepartmentId)
                                ->where('departments.is_active', true));
                    }

                    $query->orWhereHas('roles', fn ($roleQuery) => $roleQuery
                        ->where('roles.code', 'SYSTEM_ADMIN')
                        ->where('roles.is_active', true));
                })
                ->orderBy('name')
                ->get(['id', 'name']),
            'departments' => $departmentOptions->values(),
            'customFields' => $this->customFieldService->definitions(Project::class),
            'canUploadDocuments' => Gate::allows('create', Document::class),
        ]);
    }

    public function edit(Project $project): Response
    {
        Gate::authorize('update', $project);

        $project = $this->projectService->show($project);

        return Inertia::render('Projects/Edit', [
            'project' => $project,
            'departments' => $this->departmentService->list()->where('is_active', true)->values(),
            'customFields' => $this->customFieldService->definitions(Project::class),
            'customFieldValues' => $this->customFieldService->values($project),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        Gate::authorize('create', Project::class);

        $data = $request->validated();
        $hasProjectFiles = ! empty($data['project_documents'])
            || collect($data['sections'] ?? [])->contains(fn ($section) => ! empty($section['documents']));
        if ($hasProjectFiles) {
            Gate::authorize('create', Document::class);
        }

        try {
            $project = DB::transaction(function () use ($data, $request) {
                $project = $this->projectService->create(
                    data: $data,
                    creator: $request->user(),
                );

                foreach ($data['project_documents'] ?? [] as $file) {
                    $this->documentService->create(
                        data: ['title' => $file->getClientOriginalName(), 'document_type' => 'project_reference'],
                        file: $file,
                        creator: $request->user(),
                        linkable: $project,
                        purpose: 'project_reference',
                    );
                }

                $sections = $project->sections()->get();
                foreach (array_values($data['sections'] ?? []) as $index => $sectionData) {
                    $section = $sections->get($index);
                    if ($section === null) {
                        continue;
                    }

                    foreach ($sectionData['documents'] ?? [] as $file) {
                        $this->documentService->create(
                            data: ['title' => $file->getClientOriginalName(), 'document_type' => 'section_reference'],
                            file: $file,
                            creator: $request->user(),
                            linkable: $section,
                            purpose: 'section_reference',
                        );
                    }
                }

                return $project;
            });
        } catch (InvalidArgumentException $exception) {
            $field = str_contains($exception->getMessage(), 'واحد مسئول')
                ? 'sections'
                : 'manager_id';

            throw ValidationException::withMessages([
                $field => $exception->getMessage(),
            ]);
        }

        if (Gate::allows('view', $project)) {
            return redirect()->route('projects.show', $project)->with(
                'success',
                'پروژه با موفقیت ایجاد شد.'
            );
        }

        return back()->with('success', 'پروژه با موفقیت ایجاد شد.');
    }

    public function show(Request $request, Project $project): Response
    {
        Gate::authorize('view', $project);

        $canManageMembers = Gate::allows('manageMembers', $project);
        $user = $request->user();
        $hasOrganizationProjectScope = in_array(\App\Core\Permissions\Enums\PermissionScope::Organization->value, $user->permissionScopes('projects.update'), true)
            || in_array(\App\Core\Permissions\Enums\PermissionScope::Organization->value, $user->permissionScopes('projects.create'), true);
        $isProjectOwner = (int) $project->manager_id === (int) $user->id
            || (int) $project->created_by === (int) $user->id;
        $canUploadProjectDocuments = Gate::allows('create', Document::class)
            && ($isProjectOwner || $hasOrganizationProjectScope);
        $projectDetails = $this->projectService->show($project);
        $markTaskDeleteAbility = static function ($task): void {
            $task->setAttribute('can_delete', Gate::allows('delete', $task));
        };
        $projectDetails->tasks->each($markTaskDeleteAbility);
        $projectDetails->sections->each(fn ($section) => $section->tasks->each($markTaskDeleteAbility));
        $projectDetails->sections->each(function ($section): void {
            $manager = $section->department?->manager;
            $avatarPath = $manager?->profile?->avatar_path;
            if ($manager !== null) {
                $manager->setAttribute('avatar_url', $avatarPath ? Storage::url($avatarPath) : null);
            }
        });

        return Inertia::render('Projects/Show', [
            'project' => $projectDetails,
            'sectionDepartmentOptions' => Department::query()
                ->with('manager:id,name,status')
                ->where('is_active', true)
                ->whereHas('manager', fn ($query) => $query->where('status', 'active'))
                ->orderBy('name')
                ->get(['id', 'name', 'manager_id']),
            'canUpdate' => Gate::allows('update', $project),
            'canDeleteProject' => Gate::allows('delete', $project),
            'canManageMembers' => $canManageMembers,
            'canManageRoles' => Gate::allows('manageRoles', $project),
            'memberOptions' => $canManageMembers
                ? $this->projectMemberService->availableUsers($project)
                : [],
            'canUploadProjectDocuments' => $canUploadProjectDocuments,
            'canDeleteDocuments' => $user->hasPermission('documents.delete'),
            'currentUserId' => (int) $user->id,
            'managerDocumentSectionIds' => Gate::allows('create', Document::class)
                ? $project->sections()->whereHas('department', fn ($query) => $query->where('manager_id', $user->id))
                    ->pluck('id')->map(fn ($id): int => (int) $id)->values()->all()
                : [],
            'canCreateTasks' => Gate::allows('create', [Task::class, (int) $project->id]),
            'managerCreatableSectionIds' => $request->user()->hasPermission('tasks.create')
                ? $project->sections()
                    ->where('manager_can_create_tasks', true)
                    ->whereHas('department', fn ($query) => $query->where('manager_id', $request->user()->id))
                    ->pluck('id')->map(fn ($id): int => (int) $id)->values()->all()
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
