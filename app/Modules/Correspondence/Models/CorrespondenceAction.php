<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Models;

use App\Core\Departments\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrespondenceAction extends Model
{
    public const SUBMIT_RESULT = 'submit_result';
    public const MANAGER_APPROVE = 'manager_approve';
    public const MANAGER_REFER = 'manager_refer';
    public const REQUEST_TASK = 'request_task';
    public const REQUEST_PROJECT = 'request_project';
    public const REQUEST_LETTER = 'request_letter';
    public const CONFIRM_RESULT = 'confirm_result';
    public const SECRETARIAT_INITIAL_REFERRAL = 'secretariat_initial_referral';
    public const COPY_ADDED = 'copy_added';
    public const REGISTERED = 'registered';

    protected $fillable = [
        'correspondence_id',
        'actor_id',
        'action_type',
        'note',
        'from_department_id',
        'to_department_id',
        'from_user_id',
        'to_user_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
