<?php

use App\Core\Departments\Http\Controllers\DepartmentController;
use App\Core\Permissions\Http\Controllers\PermissionController;
use App\Core\Roles\Http\Controllers\RoleController;
use App\Core\Roles\Http\Controllers\RolePermissionController;
use App\Core\Users\Http\Controllers\UserController;
use App\Modules\Projects\Http\Controllers\ProjectController;
use App\Modules\Projects\Http\Controllers\ProjectMemberController;
use App\Modules\Projects\Http\Controllers\ProjectRoleController;
use App\Modules\Tasks\Http\Controllers\TaskController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */

    Route::get('/users', [UserController::class, 'index'])
        ->middleware('permission:users.view')
        ->name('users.index');

    Route::post('/users', [UserController::class, 'store'])
        ->middleware('permission:users.create')
        ->name('users.store');

    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('permission:users.edit')
        ->name('users.update');

    Route::delete('/users/{user}', [UserController::class, 'destroy'])
        ->middleware('permission:users.delete')
        ->name('users.destroy');

    Route::patch(
        '/users/{user}/toggle-status',
        [UserController::class, 'toggleStatus']
    )
        ->middleware('permission:users.toggle_status')
        ->name('users.toggle-status');

    /*
    |--------------------------------------------------------------------------
    | Departments
    |--------------------------------------------------------------------------
    */

    Route::get('/departments', [DepartmentController::class, 'index'])
        ->middleware('permission:departments.view')
        ->name('departments.index');

    Route::post('/departments', [DepartmentController::class, 'store'])
        ->middleware('permission:departments.create')
        ->name('departments.store');

    Route::put(
        '/departments/{department}',
        [DepartmentController::class, 'update']
    )
        ->middleware('permission:departments.edit')
        ->name('departments.update');

    Route::delete(
        '/departments/{department}',
        [DepartmentController::class, 'destroy']
    )
        ->middleware('permission:departments.delete')
        ->name('departments.destroy');

    Route::patch(
        '/departments/{department}/toggle-status',
        [DepartmentController::class, 'toggleStatus']
    )
        ->middleware('permission:departments.toggle_status')
        ->name('departments.toggle-status');

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */

    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('permission:roles.view')
        ->name('roles.index');

    Route::post('/roles', [RoleController::class, 'store'])
        ->middleware('permission:roles.create')
        ->name('roles.store');

    Route::put('/roles/{role}', [RoleController::class, 'update'])
        ->middleware('permission:roles.edit')
        ->name('roles.update');

    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
        ->middleware('permission:roles.delete')
        ->name('roles.destroy');

    Route::patch(
        '/roles/{role}/toggle-status',
        [RoleController::class, 'toggleStatus']
    )
        ->middleware('permission:roles.toggle_status')
        ->name('roles.toggle-status');

    /*
    |--------------------------------------------------------------------------
    | Role Permissions
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/roles/{role}/permissions',
        [RolePermissionController::class, 'edit']
    )
        ->middleware('permission:roles.manage_permissions')
        ->name('roles.permissions.edit');

    Route::put(
        '/roles/{role}/permissions',
        [RolePermissionController::class, 'update']
    )
        ->middleware('permission:roles.manage_permissions')
        ->name('roles.permissions.update');

    /*
    |--------------------------------------------------------------------------
    | Projects
    |--------------------------------------------------------------------------
    */

    Route::get('/projects', [ProjectController::class, 'index'])
        ->middleware('permission:projects.view')
        ->name('projects.index');

    Route::post('/projects', [ProjectController::class, 'store'])
        ->middleware('permission:projects.create')
        ->name('projects.store');

    Route::get('/projects/{project}', [ProjectController::class, 'show'])
        ->middleware('permission:projects.view')
        ->name('projects.show');

    Route::put('/projects/{project}', [ProjectController::class, 'update'])
        ->middleware('permission:projects.update')
        ->name('projects.update');

    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])
        ->middleware('permission:projects.delete')
        ->name('projects.destroy');

    Route::scopeBindings()->group(function () {
        Route::post('/projects/{project}/roles', [ProjectRoleController::class, 'store'])
            ->middleware('permission:projects.manage_roles')
            ->name('projects.roles.store');

        Route::put('/projects/{project}/roles/{role}', [ProjectRoleController::class, 'update'])
            ->middleware('permission:projects.manage_roles')
            ->name('projects.roles.update');

        Route::patch('/projects/{project}/roles/{role}/toggle-status', [ProjectRoleController::class, 'toggleStatus'])
            ->middleware('permission:projects.manage_roles')
            ->name('projects.roles.toggle-status');

        Route::delete('/projects/{project}/roles/{role}', [ProjectRoleController::class, 'destroy'])
            ->middleware('permission:projects.manage_roles')
            ->name('projects.roles.destroy');

        Route::post('/projects/{project}/members', [ProjectMemberController::class, 'store'])
            ->middleware('permission:projects.manage_members')
            ->name('projects.members.store');

        Route::put('/projects/{project}/members/{member}', [ProjectMemberController::class, 'updateRole'])
            ->middleware('permission:projects.manage_members')
            ->name('projects.members.update-role');

        Route::delete('/projects/{project}/members/{member}', [ProjectMemberController::class, 'destroy'])
            ->middleware('permission:projects.manage_members')
            ->name('projects.members.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Tasks
    |--------------------------------------------------------------------------
    */

    Route::get('/tasks', [TaskController::class, 'index'])
        ->middleware('permission:tasks.view')
        ->name('tasks.index');

    Route::post('/tasks', [TaskController::class, 'store'])
        ->middleware('permission:tasks.create')
        ->name('tasks.store');

    Route::get('/tasks/{task}', [TaskController::class, 'show'])
        ->middleware('permission:tasks.view')
        ->name('tasks.show');

    Route::put('/tasks/{task}', [TaskController::class, 'update'])
        ->middleware('permission:tasks.update')
        ->name('tasks.update');

    Route::patch('/tasks/{task}/assign', [TaskController::class, 'assign'])
        ->middleware('permission:tasks.assign')
        ->name('tasks.assign');

    Route::patch('/tasks/{task}/status', [TaskController::class, 'changeStatus'])
        ->middleware('permission:tasks.change_status')
        ->name('tasks.change-status');

    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])
        ->middleware('permission:tasks.delete')
        ->name('tasks.destroy');

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */
    Route::get('/permissions', [PermissionController::class, 'index'])
        ->middleware('permission:permissions.view')
        ->name('permissions.index');

    Route::post('/permissions', [PermissionController::class, 'store'])
        ->middleware('permission:permissions.create')
        ->name('permissions.store');

    Route::put(
        '/permissions/{permission}',
        [PermissionController::class, 'update']
    )
        ->middleware('permission:permissions.edit')
        ->name('permissions.update');

    Route::delete(
        '/permissions/{permission}',
        [PermissionController::class, 'destroy']
    )
        ->middleware('permission:permissions.delete')
        ->name('permissions.destroy');

    Route::patch(
        '/permissions/{permission}/toggle-status',
        [PermissionController::class, 'toggleStatus']
    )
        ->middleware('permission:permissions.toggle_status')
        ->name('permissions.toggle-status');
});

require __DIR__.'/auth.php';