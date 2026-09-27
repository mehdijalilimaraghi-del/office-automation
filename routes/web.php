<?php

use App\Core\Departments\Http\Controllers\DepartmentController;
use App\Core\Permissions\Http\Controllers\PermissionController;
use App\Core\Roles\Http\Controllers\RoleController;
use App\Core\Roles\Http\Controllers\RolePermissionController;
use App\Core\Users\Http\Controllers\UserController;
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