<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Models;

use App\Core\Departments\Models\Department;
use App\Models\User;
use App\Modules\Documents\Models\DocumentLink;
use App\Modules\Workflows\Models\WorkflowInstance;
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
        'registered_at',
        'registered_by',
        'sender_name',
        'recipient_name',
        'received_at',
        'sent_at',
        'created_by',
        'department_id',
        'workflow_instance_id',
        'reply_to_id',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'received_at' => 'datetime',
            'sent_at' => 'datetime',
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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'reply_to_id');
    }

    public function authorizedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'correspondence_user_access')
            ->withPivot(['granted_by', 'reason'])
            ->withTimestamps();
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'linkable');
    }
}
