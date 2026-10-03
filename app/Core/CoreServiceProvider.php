<?php

namespace App\Core;

use App\Core\Departments\Contracts\DepartmentServiceInterface;
use App\Core\Departments\Services\DepartmentService;
use App\Core\ExternalParties\Contracts\ExternalPartyServiceInterface;
use App\Core\ExternalParties\Services\ExternalPartyService;
use App\Core\Permissions\Contracts\PermissionServiceInterface;
use App\Core\Permissions\Services\PermissionService;
use App\Core\Roles\Contracts\RoleServiceInterface;
use App\Core\Roles\Services\RoleService;
use App\Core\Users\Contracts\UserServiceInterface;
use App\Core\Users\Services\UserService;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DepartmentServiceInterface::class, DepartmentService::class);
        $this->app->bind(RoleServiceInterface::class, RoleService::class);
        $this->app->bind(PermissionServiceInterface::class, PermissionService::class);
        $this->app->bind(UserServiceInterface::class, UserService::class);
        $this->app->bind(ExternalPartyServiceInterface::class, ExternalPartyService::class);
    }

    public function boot(): void
    {
    }
}