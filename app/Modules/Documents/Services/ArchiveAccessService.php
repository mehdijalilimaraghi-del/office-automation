<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Core\Departments\Models\Department;
use App\Models\User;
use App\Modules\Documents\Models\DepartmentArchiveSetting;
use App\Modules\Documents\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ArchiveAccessService
{
    public function departments(User $user): Collection
    {
        return Department::query()->where('is_active', true)->where(function (Builder $query) use ($user): void {
            $query->where('manager_id', $user->id)
                ->orWhereHas('members', fn (Builder $members) => $members->where('users.id', $user->id));
        })->orderBy('name')->get(['id', 'name', 'manager_id']);
    }

    public function canEnter(User $user): bool
    {
        return $user->isActive() && ($user->hasPermission('documents.view') || $this->departments($user)->isNotEmpty());
    }

    public function manages(User $user, ?int $departmentId): bool
    {
        if (! $user->isActive() || $departmentId === null) {
            return false;
        }
        $department = $this->departments($user)->firstWhere('id', $departmentId);

        return $department !== null && ((int) $department->manager_id === (int) $user->id
            || DepartmentArchiveSetting::query()->where('department_id', $departmentId)->where('archivist_id', $user->id)->exists());
    }

    public function visible(Builder $query, User $user): Builder
    {
        $departments = $this->departments($user)->pluck('id');

        return $query->where(function (Builder $query) use ($user, $departments): void {
            $query->where('created_by', $user->id)
                ->orWhereHas('accesses', fn (Builder $access) => $access->where('user_id', $user->id))
                ->orWhere(function (Builder $query) use ($departments): void {
                    $query->where('archive_restricted', false)->where(function (Builder $query) use ($departments): void {
                        $query->whereIn('related_department_id', $departments)->orWhereIn('archive_department_id', $departments);
                    });
                });
        });
    }

    public function canView(User $user, Document $document): bool
    {
        return $this->canEnter($user) && $document->is_active
            && $this->visible(Document::query()->whereKey($document->id), $user)->exists();
    }

    public function canEdit(User $user, Document $document): bool
    {
        return $this->canView($user, $document) && (
            $this->manages($user, $document->archive_department_id)
            || ((int) $document->created_by === (int) $user->id
                && ($document->archive_department_id !== null || $user->hasPermission('documents.update')))
            || ($user->hasPermission('documents.update')
                && $document->accesses()->where('user_id', $user->id)->where('access_level', 'edit')->exists())
        );
    }
}
