<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Services;

use App\Core\Settings\Services\OrganizationDepartmentRoleService;
use App\Models\User;
use App\Modules\Correspondence\Models\CorrespondenceAction;
use App\Modules\Correspondence\Models\Correspondence;
use Illuminate\Database\Eloquent\Builder;

class CorrespondenceAccessService
{
    public function isSecretariat(User $user): bool
    {
        return $user->isActive()
            && ($user->isSystemAdmin()
                || app(OrganizationDepartmentRoleService::class)->userBelongsTo(
                    $user,
                    OrganizationDepartmentRoleService::SECRETARIAT,
                ));
    }

    public function canCreateType(string $type, User $user): bool
    {
        if (! $user->isActive() || ! $user->hasPermission('correspondence.create')) {
            return false;
        }

        return in_array($type, [Correspondence::TYPE_INTERNAL, Correspondence::TYPE_OUTGOING], true)
            || ($type === Correspondence::TYPE_INCOMING && $this->isSecretariat($user));
    }

    public function canManageBeforeAction(Correspondence $correspondence, User $user): bool
    {
        // Registration, the automatic initial routing that follows it, and copy-recipient
        // bookkeeping are not user workflow actions and must not lock the correspondence.
        $hasWorkflowAction = $correspondence->actions()
            ->whereNotIn('action_type', [
                \App\Modules\Correspondence\Models\CorrespondenceAction::REGISTERED,
                \App\Modules\Correspondence\Models\CorrespondenceAction::SECRETARIAT_INITIAL_REFERRAL,
                \App\Modules\Correspondence\Models\CorrespondenceAction::COPY_ADDED,
            ])
            ->exists();

        $isReturnedForCorrection = $correspondence->type === Correspondence::TYPE_OUTGOING
            && $correspondence->status === Correspondence::STATUS_REGISTERED
            && $correspondence->workflow_state === 'returned_for_correction'
            && (int) $correspondence->created_by === (int) $user->id
            && $correspondence->actions()->where('action_type', CorrespondenceAction::SECRETARIAT_RETURN)->exists();

        if (! $user->isActive() || ($hasWorkflowAction && ! $isReturnedForCorrection)
            || $correspondence->replies()->exists() || $correspondence->task_id !== null) {
            return false;
        }

        if ($isReturnedForCorrection) {
            return $user->hasPermission('correspondence.create');
        }

        if ($correspondence->type === Correspondence::TYPE_INCOMING) {
            return $this->isSecretariat($user);
        }

        return $user->hasPermission('correspondence.create')
            && (int) $correspondence->created_by === (int) $user->id;
    }

    public function canDelete(Correspondence $correspondence, User $user): bool
    {
        if ($correspondence->workflow_state === 'returned_for_correction' && ! $user->isSystemAdmin()) {
            return false;
        }

        return $user->isActive()
            && ($user->isSystemAdmin() || $this->canManageBeforeAction($correspondence, $user));
    }

    public function canRegister(Correspondence $correspondence, User $user): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($user->isSystemAdmin()) {
            return true;
        }

        $secretariatDepartmentId = app(\\App\\Core\\Settings\\Services\\OrganizationDepartmentRoleService::class)
            ->activeDepartmentId(\\App\\Core\\Settings\\Services\\OrganizationDepartmentRoleService::SECRETARIAT);

        $isSecretariatManager = $secretariatDepartmentId !== null
            && \\App\\Core\\Departments\\Models\\Department::query()
                ->whereKey($secretariatDepartmentId)
                ->where('manager_id', $user->id)
                ->exists();

        return $isSecretariatManager || ($user->hasPermission('correspondence.create')
            && $correspondence->type === Correspondence::TYPE_INTERNAL
            && (int) $correspondence->created_by === (int) $user->id);
    }

    public function visibleToQuery(Builder $query, User $user): Builder
    {
        if (! $user->isActive() || ! $user->hasPermission('correspondence.view')) {
            return $query->whereRaw('1 = 0');
        }

        $isRegistrar = $this->isSecretariat($user);

        return $query->where(function (Builder $visible) use ($user, $isRegistrar): void {
            $visible->where(function (Builder $ordinary) use ($user): void {
                $ordinary->where('classification', Correspondence::CLASSIFICATION_ORDINARY)
                    ->where('status', Correspondence::STATUS_REGISTERED);
            });

            // Drafts are private to their creator and the Secretariat only.
            $visible->orWhere(function (Builder $draft) use ($user, $isRegistrar): void {
                $draft->where('status', Correspondence::STATUS_DRAFT);

                if (! $isRegistrar) {
                    $draft->where(function (Builder $owner) use ($user): void {
                        $owner->where('created_by', $user->id)
                            ->orWhere(function (Builder $assignedManager) use ($user): void {
                                $assignedManager->where('current_user_id', $user->id)
                                    ->where('workflow_state', 'awaiting_manager_approval')
                                    ->whereHas('currentDepartment', fn (Builder $department) => $department
                                        ->where('manager_id', $user->id));
                            })
                            ->orWhereHas('replyTo', fn (Builder $source) => $source
                                ->where('workflow_state', 'letter_draft_pending_manager')
                                ->where('current_user_id', $user->id));
                    });
                }
            });

            // Registered confidential correspondence remains visible only to related users and the Secretariat.
            $visible->orWhere(function (Builder $private) use ($user, $isRegistrar): void {
                $private->where('classification', Correspondence::CLASSIFICATION_CONFIDENTIAL)
                    ->where('status', Correspondence::STATUS_REGISTERED);

                if ($isRegistrar) {
                    return;
                }

                $private->where(function (Builder $related) use ($user): void {
                    $related->where('created_by', $user->id)
                        ->orWhere('registered_by', $user->id)
                        ->orWhere('current_user_id', $user->id)
                        ->orWhereHas('currentDepartment.members', fn (Builder $members) => $members->where('users.id', $user->id))
                        ->orWhereHas('currentDepartment', fn (Builder $departments) => $departments->where('manager_id', $user->id))
                        ->orWhereHas('authorizedUsers', fn (Builder $users) => $users->where('users.id', $user->id))
                        ->orWhereHas('task', fn (Builder $tasks) => $tasks
                            ->where('assignee_id', $user->id)
                            ->orWhere('created_by', $user->id))
                        ->orWhereHas('workflowInstance.steps.task', fn (Builder $tasks) => $tasks
                            ->where('assignee_id', $user->id)
                            ->orWhere('created_by', $user->id))
                        ->orWhereHas('copies', function (Builder $departments) use ($user): void {
                            $departments->where(function (Builder $member) use ($user): void {
                                $member->whereHas('members', fn (Builder $members) => $members->where('users.id', $user->id))
                                    ->orWhere('manager_id', $user->id);
                            });
                        });
                });
            });
        });
    }

    public function canView(Correspondence $correspondence, User $user): bool
    {
        if (! $user->isActive() || ! $user->hasPermission('correspondence.view')) {
            return false;
        }

        if ($this->isSecretariat($user)) {
            return true;
        }

        if ($correspondence->status === Correspondence::STATUS_DRAFT) {
            return (int) $correspondence->created_by === (int) $user->id
                || ((int) $correspondence->current_user_id === (int) $user->id
                    && $correspondence->workflow_state === 'awaiting_manager_approval'
                    && $correspondence->currentDepartment()->where('manager_id', $user->id)->exists())
                || $correspondence->replyTo()
                    ->where('workflow_state', 'letter_draft_pending_manager')
                    ->where('current_user_id', $user->id)
                    ->exists();
        }

        if ($correspondence->status === Correspondence::STATUS_REGISTERED
            && $correspondence->classification === Correspondence::CLASSIFICATION_ORDINARY) {
            return true;
        }

        return (int) $correspondence->created_by === (int) $user->id
            || (int) $correspondence->registered_by === (int) $user->id
            || (int) $correspondence->current_user_id === (int) $user->id
            || ($correspondence->current_department_id !== null
                && ($correspondence->currentDepartment()->whereHas('members', fn ($members) => $members->where('users.id', $user->id))->exists()
                    || (int) $correspondence->currentDepartment()->value('manager_id') === (int) $user->id))
            || $correspondence->authorizedUsers()->where('users.id', $user->id)->exists()
            || $correspondence->task()->where(function ($tasks) use ($user): void {
                $tasks->where('assignee_id', $user->id)->orWhere('created_by', $user->id);
            })->exists()
            || $correspondence->workflowInstance()->whereHas('steps.task', function ($tasks) use ($user): void {
                $tasks->where('assignee_id', $user->id)->orWhere('created_by', $user->id);
            })->exists()
            || ($correspondence->status === Correspondence::STATUS_REGISTERED
                && $correspondence->copies()->where(function ($departments) use ($user): void {
                    $departments->whereHas('members', fn ($members) => $members->where('users.id', $user->id))
                        ->orWhere('manager_id', $user->id);
                })->exists());
    }
}
