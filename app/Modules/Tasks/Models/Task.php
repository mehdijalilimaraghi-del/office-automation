<?php

namespace App\Modules\Tasks\Models;

use App\Core\Departments\Models\Department;
use App\Models\User;
use App\Core\ExternalParties\Models\ExternalOrganization;
use App\Core\ExternalParties\Models\ExternalDepartment;
use App\Core\ExternalParties\Models\ExternalContact;
use App\Modules\Projects\Models\Project;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Task extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (Task $task): void {
            TaskDependency::query()
                ->where('predecessor_task_id', $task->id)
                ->orWhere('successor_task_id', $task->id)
                ->delete();
        });
    }

    protected $fillable = [
        'project_id',
        'department_id',
        'external_organization_id',
        'external_department_id',
        'external_contact_id',
        'title',
        'description',
        'status',
        'priority',
        'review_required',
        'review_status',
        'assignee_id',
        'reviewed_by',
        'review_requested_at',
        'reviewed_at',
        'review_note',
        'created_by',
        'start_date',
        'due_date',
        'completed_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'review_required' => 'boolean',
            'reviewed_by' => 'integer',
            'review_requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo { return $this->belongsTo(Department::class, 'department_id'); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class, 'project_id'); }
    public function externalOrganization(): BelongsTo { return $this->belongsTo(ExternalOrganization::class, 'external_organization_id'); }
    public function externalDepartment(): BelongsTo { return $this->belongsTo(ExternalDepartment::class, 'external_department_id'); }
    public function externalContact(): BelongsTo { return $this->belongsTo(ExternalContact::class, 'external_contact_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assignee_id'); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function assignments(): HasMany { return $this->hasMany(TaskAssignment::class); }
    public function comments(): HasMany { return $this->hasMany(TaskComment::class); }
    public function workLogs(): HasMany { return $this->hasMany(TaskWorkLog::class); }
    public function reminders(): HasMany { return $this->hasMany(TaskReminder::class); }
    public function statusHistory(): HasMany { return $this->hasMany(TaskStatusHistory::class); }
    public function dependencies(): HasMany { return $this->hasMany(TaskDependency::class, 'successor_task_id'); }
    public function predecessors(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'successor_task_id', 'predecessor_task_id')
            ->withPivot('type');
    }
    public function successors(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'predecessor_task_id', 'successor_task_id')
            ->withPivot('type');
    }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
