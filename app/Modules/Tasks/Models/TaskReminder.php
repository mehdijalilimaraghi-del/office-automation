<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskReminder extends Model
{
    protected $fillable = ['task_id', 'user_id', 'created_by', 'title', 'description', 'remind_at', 'status', 'completed_at'];

    protected function casts(): array
    {
        return ['remind_at' => 'datetime:Y-m-d H:i:s', 'completed_at' => 'datetime'];
    }

    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
