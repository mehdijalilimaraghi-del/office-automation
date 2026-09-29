<?php

namespace App\Modules\Projects\Services;

use App\Models\User;
use App\Modules\Projects\Actions\CreateProjectAction;
use App\Modules\Projects\Actions\DeleteProjectAction;
use App\Modules\Projects\Actions\UpdateProjectAction;
use App\Modules\Projects\Contracts\ProjectServiceContract;
use App\Modules\Projects\Models\Project;
use Illuminate\Support\Collection;

class ProjectService implements ProjectServiceContract
{
    public function __construct(
        private readonly CreateProjectAction $createProjectAction,
        private readonly UpdateProjectAction $updateProjectAction,
        private readonly DeleteProjectAction $deleteProjectAction,
    ) {
    }

    public function list(): Collection
    {
        return Project::query()
            ->with(['manager', 'creator'])
            ->latest()
            ->get();
    }

    public function show(Project $project): Project
    {
        return $project->load([
            'manager',
            'creator',
            'roles',
            'members.user',
            'members.projectRole',
            'tasks.assignee',
            'tasks.creator',
        ]);
    }

    public function create(
        array $data,
        User $creator
    ): Project {
        return $this->createProjectAction->execute(
            data: $data,
            creator: $creator
        );
    }

    public function update(
        Project $project,
        array $data
    ): Project {
        return $this->updateProjectAction->execute(
            project: $project,
            data: $data
        );
    }

    public function delete(
        Project $project
    ): void {
        $this->deleteProjectAction->execute(
            project: $project
        );
    }
}