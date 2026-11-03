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
    public const MANAGER_RECALL_REFERRAL = 'manager_recall_referral';
    public const MANAGER_CREATE_TASK = 'manager_create_task';
    public const MANAGER_COMPLETE_WORKFLOW = 'manager_complete_workflow';
    public const REQUEST_TASK = 'request_task';
    public const REQUEST_PROJECT = 'request_project';
    public const REQUEST_LETTER = 'request_letter';
    public const EMPLOYEE_REQUEST_NONE = 'employee_request_none';
    public const EMPLOYEE_REQUEST_TASK = 'employee_request_task';
    public const EMPLOYEE_REQUEST_LETTER = 'employee_request_letter';
    public const EMPLOYEE_REQUEST_WORKFLOW = 'employee_request_workflow';
    public const EMPLOYEE_REQUEST_PROJECT = 'employee_request_project';
    public const MANAGER_REJECT_EMPLOYEE_REQUEST = 'manager_reject_employee_request';
    public const MANAGER_REFER_PROJECT_MANAGEMENT = 'manager_refer_project_management';
    public const MANAGER_CREATE_REQUESTED_LETTER = 'manager_create_requested_letter';
    public const MANAGER_ASSIGN_LETTER_DRAFT = 'manager_assign_letter_draft';
    public const MANAGER_START_REQUESTED_WORKFLOW = 'manager_start_requested_workflow';
    public const REQUESTED_LETTER_DRAFT_CREATED = 'requested_letter_draft_created';
    public const REQUESTED_LETTER_REGISTERED = 'requested_letter_registered';
    public const PM_REQUEST_REFER = 'pm_request_refer';
    public const PM_REQUEST_PROJECT = 'pm_request_project';
    public const PM_REQUEST_REJECT = 'pm_request_reject';
    public const PM_REQUEST_RETURN = 'pm_request_return';
    public const PM_MANAGER_APPROVE_REQUEST = 'pm_manager_approve_request';
    public const PM_MANAGER_REJECT_REQUEST = 'pm_manager_reject_request';
    public const CONFIRM_RESULT = 'confirm_result';
    public const SECRETARIAT_INITIAL_REFERRAL = 'secretariat_initial_referral';
    public const COPY_ADDED = 'copy_added';
    public const REGISTERED = 'registered';
    public const DISPATCHED = 'dispatched';
    public const SECRETARIAT_RETURN = 'secretariat_return';
    public const RESUBMIT_TO_SECRETARIAT = 'resubmit_to_secretariat';

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
