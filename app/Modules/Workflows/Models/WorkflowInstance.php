<?php

declare(strict_types=1);

namespace App\Modules\Workflows\Models;

use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Core\Departments\Models\Department;
use App\Modules\Documents\Models\DocumentLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class WorkflowInstance extends Model
{
    protected $fillable = [
        'workflow_definition_id',
        'initiated_by',
        'project_id',
        'department_id',
        'status',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowInstanceStep::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'linkable');
    }
}
