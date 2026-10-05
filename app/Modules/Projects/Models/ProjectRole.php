<?php

namespace App\Modules\Projects\Models;

use App\Models\Concerns\HasSystemCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectRole extends Model
{
    use HasSystemCode;

use HasFactory;

    protected static function systemCodePrefix(): string
    {
        return 'PRL';
    }

    protected $fillable = [
        'project_id',
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(
            Project::class,
            'project_id'
        );
    }

    public function members(): HasMany
    {
        return $this->hasMany(
            ProjectMember::class,
            'project_role_id'
        );
    }
}