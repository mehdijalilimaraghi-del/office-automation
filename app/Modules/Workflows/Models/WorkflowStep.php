<?php

declare(strict_types=1);

namespace App\Modules\Workflows\Models;

use App\Modules\Core\Models\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStep extends Model
{
    protected $fillable = [
        'workflow_definition_id',
        'step_order',
        'name',
        'task_title',
        'task_description',
        'actor_type',
        'actor_id',
        'department_id',
        'review_required',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
            'actor_id' => 'integer',
            'review_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function instanceSteps(): HasMany
    {
        return $this->hasMany(WorkflowInstanceStep::class);
    }
};