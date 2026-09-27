<?php

namespace App\Models;

use App\Core\Departments\Models\Department;
use App\Core\Roles\Models\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'department_id',
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

    public function department(): BelongsTo
    {
        return $this->belongsTo(
            Department::class,
            'department_id'
        );
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_user'
        )->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    public function hasRole(string $roleCode): bool
    {
        return $this->roles()
            ->where('roles.code', $roleCode)
            ->where('roles.is_active', true)
            ->exists();
    }

    public function hasPermission(string $permissionCode): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        return $this->roles()
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
    }

    public function hasAnyPermission(
        array $permissionCodes
    ): bool {
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

    public function hasAllPermissions(
        array $permissionCodes
    ): bool {
        foreach ($permissionCodes as $permissionCode) {
            if (! $this->hasPermission($permissionCode)) {
                return false;
            }
        }

        return true;
    }
}