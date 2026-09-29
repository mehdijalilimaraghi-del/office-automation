<?php

declare(strict_types=1);

namespace App\Modules\Projects\Contracts;

use App\Models\User;
use App\Modules\Projects\Models\Project;
use Illuminate\Support\Collection;

interface ProjectServiceContract
{
    public function list(): Collection;

    public function show(Project $project): Project;

    public function create(
        array $data,
        User $creator
    ): Project;

    public function update(
        Project $project,
        array $data
    ): Project;

    public function delete(
        Project $project
    ): void;
}