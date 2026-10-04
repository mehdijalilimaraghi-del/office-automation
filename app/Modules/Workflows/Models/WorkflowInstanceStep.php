<?php

declare(strict_types=1);

namespace App\Modules\Workflows\Models;

use App\Modules\Tasks\Models\Task;
use App\Modules\Documents\Models\DocumentLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class WorkflowInstanceStep extends Model
{
    protected $fillable = [
        'workflow_instance_id',
        'workflow_step_id',
        'task_id',
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

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'linkable');
    }
}
