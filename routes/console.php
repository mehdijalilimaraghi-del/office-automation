<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Core\Departments\Models\Department;
use App\Modules\Projects\Actions\EnsureDepartmentProjectSectionAction;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectSection;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Artisan::command('projects:sync-organization-sections', function (EnsureDepartmentProjectSectionAction $ensureSection): int {
    $synced = 0;

    Department::query()->orderBy('id')->chunkById(100, function ($departments) use ($ensureSection, &$synced): void {
        foreach ($departments as $department) {
            $ensureSection->execute($department);
            $synced++;
        }
    });

    $project = Project::query()
        ->where('is_system_default', true)
        ->whereNull('department_id')
        ->first();

    $duplicates = $project === null
        ? collect()
        : ProjectSection::query()
            ->select('department_id')
            ->where('project_id', $project->id)
            ->whereNotNull('department_id')
            ->groupBy('department_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('department_id');

    $this->info("بخش‌های واحدها همگام‌سازی شدند: {$synced}");

    if ($duplicates->isNotEmpty()) {
        $this->warn('برای واحدهای زیر بیش از یک بخش وجود دارد؛ به دلیل احتمال وجود سوابق، حذف خودکار انجام نشد: '.$duplicates->implode(', '));
    } else {
        $this->info('بخش تکراری برای واحدها یافت نشد.');
    }

    return 0;
})->purpose('همگام‌سازی بخش‌های پروژه عمومی با واحدهای سازمانی');
