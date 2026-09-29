<?php

declare(strict_types=1);

namespace App\Modules\Projects\Actions;

use App\Modules\Projects\Models\Project;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class DeleteProjectAction
{
    public function execute(Project $project): void
    {
        if ($project->is_system_default) {
            throw ValidationException::withMessages([
                'project' => 'پروژه عمومی و پیش‌فرض دپارتمان قابل حذف نیست.',
            ]);
        }

        DB::transaction(function () use ($project): void {
            $project->delete();
        });
    }
}