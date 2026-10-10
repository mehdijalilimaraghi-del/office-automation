<?php

use App\Core\Departments\Http\Controllers\DepartmentController;
use App\Core\Departments\Http\Controllers\PositionController;
use App\Core\Permissions\Http\Controllers\PermissionController;
use App\Core\Roles\Http\Controllers\RoleController;
use App\Core\Roles\Http\Controllers\RolePermissionController;
use App\Core\Users\Http\Controllers\UserController;
use App\Core\Users\Http\Controllers\UserInvitationController;
use App\Core\CustomFields\Http\Controllers\CustomFieldController;
use App\Core\Settings\Http\Controllers\OrganizationSettingsController;
use App\Core\ExternalParties\Http\Controllers\ExternalPartyController;
use App\Modules\Projects\Http\Controllers\ProjectController;
use App\Modules\Projects\Http\Controllers\ProjectMemberController;
use App\Modules\Projects\Http\Controllers\ProjectRoleController;
use App\Modules\Tasks\Http\Controllers\TaskController;
use App\Modules\Documents\Http\Controllers\DocumentController;
use App\Http\Controllers\ProfileController;
use App\Modules\Dashboard\Http\Controllers\DashboardController;
use App\Modules\Cartable\Http\Controllers\CartableController;
use App\Modules\Notifications\Http\Controllers\NotificationController;
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

Route::get('/users/invitations/{token}', [UserInvitationController::class, 'accept'])->middleware('guest')->name('users.invitation.accept');
Route::post('/users/invitations', [UserInvitationController::class, 'store'])->middleware('guest')->name('users.invitation.store');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/cartable', [CartableController::class, 'index'])->middleware('permission:cartable.view')->name('cartable.index');

    Route::get('/notifications', [NotificationController::class, 'index'])->middleware('permission:notifications.view')->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->middleware('permission:notifications.view')->name('notifications.read');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->middleware('permission:notifications.view')->name('notifications.read-all');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.edit')->name('users.update');
    Route::post('/users/{user}/invite', [UserController::class, 'invite'])->middleware('permission:users.invite')->name('users.invite');
    Route::put('/users/{user}/departments', [UserController::class, 'syncDepartments'])->middleware('permission:users.manage_departments')->name('users.departments.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');
    Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->middleware('permission:users.toggle_status')->name('users.toggle-status');

    Route::get('/settings/organization', [OrganizationSettingsController::class, 'edit'])
        ->middleware('permission:settings.manage')
        ->name('settings.organization.edit');
    Route::put('/settings/organization', [OrganizationSettingsController::class, 'update'])
        ->middleware('permission:settings.manage')
        ->name('settings.organization.update');

    Route::get('/custom-fields', [CustomFieldController::class, 'index'])->middleware('permission:custom_fields.manage')->name('custom-fields.index');
    Route::post('/custom-fields', [CustomFieldController::class, 'store'])->middleware('permission:custom_fields.manage')->name('custom-fields.store');
    Route::put('/custom-fields/{customField}', [CustomFieldController::class, 'update'])->middleware('permission:custom_fields.manage')->name('custom-fields.update');
    Route::delete('/custom-fields/{customField}', [CustomFieldController::class, 'destroy'])->middleware('permission:custom_fields.manage')->name('custom-fields.destroy');

    Route::get('/departments', [DepartmentController::class, 'index'])->middleware('permission:departments.view')->name('departments.index');
    Route::post('/departments', [DepartmentController::class, 'store'])->middleware('permission:departments.create')->name('departments.store');
    Route::put('/departments/{department}', [DepartmentController::class, 'update'])->middleware('permission:departments.edit')->name('departments.update');
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->middleware('permission:departments.delete')->name('departments.destroy');
    Route::patch('/departments/{department}/toggle-status', [DepartmentController::class, 'toggleStatus'])->middleware('permission:departments.toggle_status')->name('departments.toggle-status');
    Route::post('/departments/{department}/positions', [PositionController::class, 'store'])->middleware('permission:departments.manage_positions')->name('departments.positions.store');
    Route::put('/departments/{department}/positions/{position}', [PositionController::class, 'update'])->middleware('permission:departments.manage_positions')->name('departments.positions.update');
    Route::delete('/departments/{department}/positions/{position}', [PositionController::class, 'destroy'])->middleware('permission:departments.manage_positions')->name('departments.positions.destroy');
    Route::post('/departments/{department}/positions/{position}/members', [PositionController::class, 'assign'])->middleware('permission:departments.manage_positions')->name('departments.positions.members.store');
    Route::delete('/departments/{department}/positions/{position}/members/{user}', [PositionController::class, 'unassign'])->middleware('permission:departments.manage_positions')->name('departments.positions.members.destroy');

    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.edit')->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('roles.destroy');
    Route::patch('/roles/{role}/toggle-status', [RoleController::class, 'toggleStatus'])->middleware('permission:roles.toggle_status')->name('roles.toggle-status');

    Route::get('/roles/{role}/permissions', [RolePermissionController::class, 'edit'])->middleware('permission:roles.manage_permissions')->name('roles.permissions.edit');
    Route::put('/roles/{role}/permissions', [RolePermissionController::class, 'update'])->middleware('permission:roles.manage_permissions')->name('roles.permissions.update');

    Route::get('/projects', [ProjectController::class, 'index'])->middleware('permission:projects.view')->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->middleware('permission:projects.create')->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->middleware('permission:projects.create')->name('projects.store');
    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->middleware('permission:projects.update')->name('projects.edit');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->middleware('permission:projects.view')->name('projects.show');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->middleware('permission:projects.update')->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->middleware('permission:projects.delete')->name('projects.destroy');

    Route::scopeBindings()->group(function () {
        Route::post('/projects/{project}/roles', [ProjectRoleController::class, 'store'])->middleware('permission:projects.manage_roles')->name('projects.roles.store');
        Route::put('/projects/{project}/roles/{role}', [ProjectRoleController::class, 'update'])->middleware('permission:projects.manage_roles')->name('projects.roles.update');
        Route::patch('/projects/{project}/roles/{role}/toggle-status', [ProjectRoleController::class, 'toggleStatus'])->middleware('permission:projects.manage_roles')->name('projects.roles.toggle-status');
        Route::delete('/projects/{project}/roles/{role}', [ProjectRoleController::class, 'destroy'])->middleware('permission:projects.manage_roles')->name('projects.roles.destroy');
        Route::post('/projects/{project}/members', [ProjectMemberController::class, 'store'])->middleware('permission:projects.manage_members')->name('projects.members.store');
        Route::put('/projects/{project}/members/{member}', [ProjectMemberController::class, 'updateRole'])->middleware('permission:projects.manage_members')->name('projects.members.update-role');
        Route::delete('/projects/{project}/members/{member}', [ProjectMemberController::class, 'destroy'])->middleware('permission:projects.manage_members')->name('projects.members.destroy');
    });

    Route::get('/tasks', [TaskController::class, 'index'])->middleware('permission:tasks.view')->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->middleware('permission:tasks.create')->name('tasks.store');
    Route::post('/tasks/{task}/documents', [DocumentController::class, 'storeForTask'])
        ->middleware('permission:documents.create')
        ->name('tasks.documents.store');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->middleware('permission:tasks.view')->name('tasks.show');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->middleware('permission:tasks.update')->name('tasks.update');
    Route::put('/tasks/{task}/external-parties', [TaskController::class, 'updateExternalParties'])->middleware('permission:tasks.update')->name('tasks.external-parties.update');
    Route::patch('/tasks/{task}/assign', [TaskController::class, 'assign'])->middleware('permission:tasks.assign')->name('tasks.assign');
    Route::patch('/tasks/{task}/refer', [TaskController::class, 'refer'])->middleware('permission:tasks.refer')->name('tasks.refer');
    Route::put('/tasks/{task}/participants', [TaskController::class, 'updateParticipants'])->middleware('permission:tasks.update')->name('tasks.participants.update');
    Route::post('/tasks/{task}/activities', [TaskController::class, 'storeActivity'])->middleware('permission:tasks.update')->name('tasks.activities.store');
    Route::patch('/tasks/{task}/activities/{activity}/complete', [TaskController::class, 'completeActivity'])->middleware('permission:tasks.update')->name('tasks.activities.complete');
    Route::put('/tasks/{task}/activities/{activity}', [TaskController::class, 'updateActivity'])->middleware('permission:tasks.update')->name('tasks.activities.update');
    Route::delete('/tasks/{task}/activities/{activity}', [TaskController::class, 'destroyActivity'])->middleware('permission:tasks.update')->name('tasks.activities.destroy');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'changeStatus'])->middleware('permission:tasks.change_status')->name('tasks.change-status');

    Route::post('/project-sections/{section}/documents', [DocumentController::class, 'storeForSection'])
        ->middleware('permission:documents.create')
        ->name('documents.sections.store');

    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])
        ->middleware('permission:documents.view')
        ->name('documents.download');

    Route::patch('/tasks/{task}/submit', [TaskController::class, 'submit'])->middleware('permission:tasks.submit')->name('tasks.submit');
    Route::patch('/tasks/{task}/approve', [TaskController::class, 'approve'])->middleware('permission:tasks.review')->name('tasks.approve');
    Route::patch('/tasks/{task}/return', [TaskController::class, 'returnFromReview'])->middleware('permission:tasks.review')->name('tasks.return');
    Route::post('/tasks/{task}/comments', [TaskController::class, 'comment'])->middleware('permission:tasks.comment')->name('tasks.comments.store');
    Route::put('/tasks/{task}/comments/{comment}', [TaskController::class, 'updateComment'])->middleware('permission:tasks.update')->name('tasks.comments.update');
    Route::delete('/tasks/{task}/comments/{comment}', [TaskController::class, 'destroyComment'])->middleware('permission:tasks.update')->name('tasks.comments.destroy');
    Route::post('/tasks/{task}/work-logs', [TaskController::class, 'workLog'])->middleware('permission:tasks.log_work')->name('tasks.work-logs.store');
    Route::post('/tasks/{task}/reminders', [TaskController::class, 'reminder'])->middleware('permission:tasks.create_reminder')->name('tasks.reminders.store');
    Route::put('/tasks/{task}/reminders/{reminder}', [TaskController::class, 'updateReminder'])->middleware('permission:tasks.update')->name('tasks.reminders.update');
    Route::delete('/tasks/{task}/reminders/{reminder}', [TaskController::class, 'destroyReminder'])->middleware('permission:tasks.update')->name('tasks.reminders.destroy');

    Route::scopeBindings()->group(function () {
        Route::patch('/tasks/{task}/reminders/{reminder}/status', [TaskController::class, 'updateReminderStatus'])
            ->middleware('permission:tasks.view')
            ->name('tasks.reminders.update-status');
    });

    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->middleware('permission:tasks.delete')->name('tasks.destroy');

    Route::get('/external-parties', [ExternalPartyController::class, 'index'])->middleware('permission:external_parties.view')->name('external-parties.index');
    Route::post('/external-parties/organizations', [ExternalPartyController::class, 'storeOrganization'])->middleware('permission:external_parties.create')->name('external-parties.organizations.store');
    Route::put('/external-parties/organizations/{organization}', [ExternalPartyController::class, 'updateOrganization'])->middleware('permission:external_parties.edit')->name('external-parties.organizations.update');
    Route::delete('/external-parties/organizations/{organization}', [ExternalPartyController::class, 'destroyOrganization'])->middleware('permission:external_parties.delete')->name('external-parties.organizations.destroy');
    Route::post('/external-parties/departments', [ExternalPartyController::class, 'storeDepartment'])->middleware('permission:external_parties.create')->name('external-parties.departments.store');
    Route::put('/external-parties/departments/{department}', [ExternalPartyController::class, 'updateDepartment'])->middleware('permission:external_parties.edit')->name('external-parties.departments.update');
    Route::delete('/external-parties/departments/{department}', [ExternalPartyController::class, 'destroyDepartment'])->middleware('permission:external_parties.delete')->name('external-parties.departments.destroy');
    Route::post('/external-parties/contacts', [ExternalPartyController::class, 'storeContact'])->middleware('permission:external_parties.create')->name('external-parties.contacts.store');
    Route::put('/external-parties/contacts/{contact}', [ExternalPartyController::class, 'updateContact'])->middleware('permission:external_parties.edit')->name('external-parties.contacts.update');
    Route::delete('/external-parties/contacts/{contact}', [ExternalPartyController::class, 'destroyContact'])->middleware('permission:external_parties.delete')->name('external-parties.contacts.destroy');

    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:permissions.view')->name('permissions.index');
    Route::post('/permissions', [PermissionController::class, 'store'])->middleware('permission:permissions.create')->name('permissions.store');
    Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->middleware('permission:permissions.edit')->name('permissions.update');
    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('permission:permissions.delete')->name('permissions.destroy');
    Route::patch('/permissions/{permission}/toggle-status', [PermissionController::class, 'toggleStatus'])->middleware('permission:permissions.toggle_status')->name('permissions.toggle-status');
});

require __DIR__.'/auth.php';
