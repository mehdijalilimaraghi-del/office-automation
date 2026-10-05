<?php

namespace App\Core\Roles\Models;

use App\Core\Permissions\Models\Permission;
use App\Core\CustomFields\Models\CustomFieldValue;
use App\Models\Concerns\HasSystemCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasSystemCode;

    protected static function systemCodePrefix(): string
    {
        return 'ROLE';
    }

    protected function shouldGenerateSystemCode(): bool
    {
        return ! $this->is_system;
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'entity_id')
            ->where('entity_type', self::class);
    }

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'permission_role'
        )->withPivot('scope')
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'role_user'
        )->withTimestamps();
    }
}
