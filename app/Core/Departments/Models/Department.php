<?php

namespace App\Core\Departments\Models;

use App\Models\User;
use App\Core\CustomFields\Models\CustomFieldValue;
use App\Models\Concerns\HasSystemCode;
use App\Modules\Projects\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Department extends Model
{
    use HasSystemCode;

    protected static function systemCodePrefix(): string
    {
        return 'ORG';
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'entity_id')
            ->where('entity_type', self::class);
    }

    protected $fillable = [
        'parent_id',
        'manager_id',
        'name',
        'code',
        'icon',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'parent_id' => 'integer',
            'manager_id' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function defaultProject(): HasOne
    {
        return $this->hasOne(
            Project::class,
            'department_id'
        )->where('is_system_default', true);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'department_memberships',
            'department_id',
            'user_id'
        )->withPivot([
            'is_primary',
            'joined_at',
        ])->withTimestamps();
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class)->orderBy('name');
    }

    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Return all descendant department IDs.
     *
     * @return array<int>
     */
    public function descendantIds(): array
    {
        $ids = [];

        /** @var Collection<int, Department> $children */
        $children = $this->children()->get();

        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->descendantIds());
        }

        return $ids;
    }
}
