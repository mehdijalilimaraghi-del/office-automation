<?php

namespace App\Models;

use App\Core\Departments\Models\Department;
use App\Core\Permissions\Enums\PermissionScope;
use App\Core\Departments\Models\Position;
use App\Core\Departments\Models\PositionAssignment;
use App\Core\Roles\Models\Role;
use App\Core\Users\Models\UserProfile;
use App\Core\CustomFields\Models\CustomFieldValue;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Tasks\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'mobile',
        'phone',
        'extension',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'entity_id')
            ->where('entity_type', self::class);
    }

    public function departmentMemberships(): HasMany
    {
        return $this->hasMany(
            \App\Core\Departments\Models\DepartmentMembership::class,
            'user_id'
        );
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(
            Department::class,
            'department_memberships',
            'user_id',
            'department_id'
        )->withPivot([
            'is_primary',
            'joined_at',
        ])->withTimestamps();
    }

    public function managedDepartments(): HasMany
    {
        return $this->hasMany(
            Department::class,
            'manager_id'
        );
    }

    public function positionAssignments(): HasMany
    {
        return $this->hasMany(PositionAssignment::class);
    }

    public function positions(): BelongsToMany
    {
        return $this->belongsToMany(Position::class, 'position_assignments', 'user_id', 'position_id')
            ->withPivot(['is_primary', 'is_active', 'started_at', 'ended_at'])
            ->withTimestamps();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_user'
        )->withTimestamps();
    }

    public function managedProjects(): HasMany
    {
        return $this->hasMany(
            Project::class,
            'manager_id'
        );
    }

    public function createdProjects(): HasMany
    {
        return $this->hasMany(
            Project::class,
            'created_by'
        );
    }

    public function projectMemberships(): HasMany
    {
        return $this->hasMany(
            ProjectMember::class,
            'user_id'
        );
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(
            Project::class,
            'project_user',
            'user_id',
            'project_id'
        )->withPivot('project_role_id')
            ->withTimestamps();
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(
            Task::class,
            'created_by'
        );
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(
            Task::class,
            'assignee_id'
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    public function isSystemAdmin(): bool
    {
        return $this->hasRole('SYSTEM_ADMIN');
    }

    /**
     * Restrict user queries to operational users.
     *
     * System administrator accounts are infrastructure accounts and should
     * not be selectable for operational responsibilities such as department
     * managers, project managers, members, or task assignees.
     */
    public function scopeOperational(Builder $query): Builder
    {
        return $query->whereDoesntHave(
            'roles',
            fn ($roleQuery) => $roleQuery->where('roles.code', 'SYSTEM_ADMIN')
                ->where('roles.is_active', true)
        );
    }

    public function hasRole(string $roleCode): bool
    {
        return $this->roles()
            ->where('roles.code', $roleCode)
            ->where('roles.is_active', true)
            ->exists();
    }

    /**
     * Return all access scopes granted to the user for a permission.
     *
     * Scopes are combined across the user's active roles.
     *
     * @return array<int, string>
     */
    public function permissionScopes(string $permissionCode): array
    {
        if (! $this->isActive()) {
            return [];
        }

        $scopes = $this->roles()
            ->where('roles.is_active', true)
            ->whereHas(
                'permissions',
                function ($query) use ($permissionCode): void {
                    $query
                        ->where('permissions.code', $permissionCode)
                        ->where('permissions.is_active', true);
                }
            )
            ->with([
                'permissions' => function ($query) use ($permissionCode): void {
                    $query
                        ->where('permissions.code', $permissionCode)
                        ->where('permissions.is_active', true);
                },
            ])
            ->get()
            ->flatMap(
                fn (Role $role) => $role->permissions
                    ->map(fn ($permission) => $permission->pivot->scope)
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        return array_values(array_unique([
            ...$scopes,
            ...$this->managerDerivedScopes($permissionCode),
        ]));
    }

    public function hasPermission(string $permissionCode): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        $hasAssignedPermission = $this->roles()
            ->where('roles.is_active', true)
            ->whereHas(
                'permissions',
                function ($query) use ($permissionCode): void {
                    $query
                        ->where('permissions.code', $permissionCode)
                        ->where('permissions.is_active', true);
                }
            )
            ->exists();

        return $hasAssignedPermission || $this->managerDerivedScopes($permissionCode) !== [];
    }

    /**
     * Managers receive read-only visibility within their actual responsibility
     * even when their role does not explicitly include the list-view permission.
     *
     * @return array<int, string>
     */
    private function managerDerivedScopes(string $permissionCode): array
    {
        if (! in_array($permissionCode, ['projects.view', 'tasks.view'], true)) {
            return [];
        }

        $scopes = [];

        if ($this->managedProjects()->exists()) {
            $scopes[] = PermissionScope::Project->value;
        }

        if ($this->managedDepartments()->exists()) {
            $scopes[] = PermissionScope::Department->value;
        }

        if ($permissionCode === 'tasks.view'
            && ($this->assignedTasks()->exists() || $this->createdTasks()->exists())) {
            $scopes[] = PermissionScope::Self->value;
        }

        return $scopes;
    }

    public function hasAnyPermission(array $permissionCodes): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        return $this->roles()
            ->where('roles.is_active', true)
            ->whereHas(
                'permissions',
                function ($query) use ($permissionCodes): void {
                    $query
                        ->whereIn(
                            'permissions.code',
                            $permissionCodes
                        )
                        ->where('permissions.is_active', true);
                }
            )
            ->exists();
    }

    public function hasAllPermissions(array $permissionCodes): bool
    {
        foreach ($permissionCodes as $permissionCode) {
            if (! $this->hasPermission($permissionCode)) {
                return false;
            }
        }

        return true;
    }
}
