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

        if ($project->tasks()->exists()
            || $project->sections()->whereHas('tasks')->exists()
            || $project->documents()->exists()
            || $project->sections()->whereHas('documents')->exists()) {
            throw ValidationException::withMessages([
                'project' => 'این پروژه دارای دستورکار یا سند وابسته است؛ برای حفظ سوابق، ابتدا آن‌ها را تعیین تکلیف یا پروژه را غیرفعال کنید.',
            ]);
        }

        DB::transaction(function () use ($project): void {
            $project->delete();
        });
    }
}