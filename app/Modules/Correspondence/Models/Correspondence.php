<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Models;

use App\Core\Departments\Models\Department;
use App\Core\ExternalParties\Models\ExternalOrganization;
use App\Core\ExternalParties\Models\ExternalDepartment;
use App\Models\User;
use App\Modules\Documents\Models\DocumentLink;
use App\Modules\Workflows\Models\WorkflowInstance;
use App\Modules\Tasks\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Correspondence extends Model
{
    public const TYPE_INCOMING = 'incoming';

    public const TYPE_OUTGOING = 'outgoing';

    public const TYPE_INTERNAL = 'internal';

    public const CLASSIFICATION_ORDINARY = 'ordinary';

    public const CLASSIFICATION_CONFIDENTIAL = 'confidential';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_REGISTERED = 'registered';

    protected $fillable = [
        'type',
        'classification',
        'subject',
        'body',
        'status',
        'registration_number',
        'letter_number',
        'registered_at',
        'registered_by',
        'sender_name',
        'recipient_name',
        'received_at',
        'sent_at',
        'created_by',
        'created_department_id',
        'recipient_department_id',
        'external_organization_id',
        'external_department_id',
        'workflow_instance_id',
        'reply_to_id',
        'task_id',
        'project_id',
        'external_registration_number',
        'external_registered_at',
        'dispatch_method',
        'external_tracking_notes',
        'workflow_state',
        'current_department_id',
        'current_user_id',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'received_at' => 'datetime',
            'sent_at' => 'datetime',
            'external_registered_at' => 'date',
        ];
    }

    public static function allowedTypes(): array
    {
        return [
            self::TYPE_INCOMING,
            self::TYPE_OUTGOING,
            self::TYPE_INTERNAL,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function createdDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'created_department_id');
    }

    public function recipientDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'recipient_department_id');
    }

    public function currentDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'current_department_id');
    }

    public function externalOrganization(): BelongsTo
    {
        return $this->belongsTo(ExternalOrganization::class, 'external_organization_id');
    }

    public function externalDepartment(): BelongsTo
    {
        return $this->belongsTo(ExternalDepartment::class, 'external_department_id');
    }

    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(\\App\\Modules\\Projects\\Models\\Project::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'reply_to_id');
    }

    public function relatedLetters(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'correspondence_related_letters',
            'correspondence_id',
            'related_correspondence_id',
        )->withTimestamps();
    }

    public function relatedFromLetters(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'correspondence_related_letters',
            'related_correspondence_id',
            'correspondence_id',
        )->withTimestamps();
    }

    public function authorizedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'correspondence_user_access')
            ->withPivot(['granted_by', 'reason'])
            ->withTimestamps();
    }

    public function actions(): HasMany
    {
        return $this->hasMany(CorrespondenceAction::class)->latest();
    }

    public function copies(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'correspondence_copies', 'correspondence_id', 'department_id')
            ->withPivot(['created_by', 'note'])
            ->withTimestamps();
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'linkable');
    }
}
