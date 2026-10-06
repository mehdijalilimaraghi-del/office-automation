<?php

declare(strict_types=1);

namespace App\Modules\Projects\Contracts;

use App\Models\User;
use App\Modules\Projects\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProjectServiceContract
{
    public function list(User $user): LengthAwarePaginator;

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