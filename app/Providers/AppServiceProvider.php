<?php

namespace App\Providers;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Policies\ProjectPolicy;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Policies\TaskPolicy;
use Illuminate\Support\Facades\Gate;
use App\Modules\Projects\Contracts\ProjectMemberServiceContract;
use App\Modules\Projects\Contracts\ProjectRoleServiceContract;
use App\Modules\Projects\Contracts\ProjectServiceContract;
use App\Modules\Projects\Services\ProjectMemberService;
use App\Modules\Projects\Services\ProjectRoleService;
use App\Modules\Projects\Services\ProjectService;
use App\Modules\Tasks\Contracts\TaskServiceContract;
use App\Modules\Tasks\Services\TaskService;
use App\Core\CustomFields\Contracts\CustomFieldServiceInterface;
use App\Core\CustomFields\Services\CustomFieldService;
use App\Modules\Documents\Contracts\DocumentStorageContract;
use App\Modules\Documents\Infrastructure\FilesystemDocumentStorage;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Policies\DocumentPolicy;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CustomFieldServiceInterface::class, CustomFieldService::class);

        $this->app->bind(
            ProjectServiceContract::class,
            ProjectService::class
        );

        $this->app->bind(
            ProjectRoleServiceContract::class,
            ProjectRoleService::class
        );

        $this->app->bind(
            ProjectMemberServiceContract::class,
            ProjectMemberService::class
        );

        $this->app->bind(
            TaskServiceContract::class,
            TaskService::class
        );

        $this->app->bind(
            DocumentStorageContract::class,
            FilesystemDocumentStorage::class
        );
    }

    public function boot(): void
    {
        Gate::policy(Project::class, ProjectPolicy::class);
	Gate::policy(Task::class, TaskPolicy::class);
    }
}