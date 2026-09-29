<?php

declare(strict_types=1);

namespace App\Modules\Projects\Actions;

use App\Modules\Projects\Models\Project;
use Illuminate\Support\Facades\DB;

class DeleteProjectAction
{
    public function execute(Project $project): void
    {
        DB::transaction(function () use ($project): void {
            $project->delete();
        });
    }
}