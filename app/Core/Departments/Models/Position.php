<?php

namespace App\Core\Departments\Models;

use App\Models\User;
use App\Core\CustomFields\Models\CustomFieldValue;
use App\Models\Concerns\HasSystemCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    use HasSystemCode;

    protected static function systemCodePrefix(): string
    {
        return 'POS';
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'entity_id')
            ->where('entity_type', self::class);
    }

    protected $fillable = ['department_id','name','code','description','is_active'];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function assignments(): HasMany { return $this->hasMany(PositionAssignment::class); }
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'position_assignments', 'position_id', 'user_id')
            ->withPivot(['is_primary','is_active','started_at','ended_at'])->withTimestamps();
    }
}