<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskActivity extends Model
{
    protected $fillable = [
        'task_id',
        'title',
        'description',
        'status',
        'activity_type',
        'sequence',
        'assigned_to',
        'created_by',
        'completed_by',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
