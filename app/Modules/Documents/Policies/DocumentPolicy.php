<?php

declare(strict_types=1);

namespace App\Modules\Documents\Policies;

use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Documents\Models\Document;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectSection;
use App\Modules\Tasks\Models\Task;
use Illuminate\Support\Facades\Gate;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->hasPermission('documents.view');
    }

    public function view(User $user, Document $document): bool
    {
        if (! $user->isActive() || ! $document->is_active) {
            return false;
        }

        // Explicit document-level access still requires the document permission.
        // Attachments linked to business records inherit access to that record.
        if ($user->hasPermission('documents.view')) {
            if ((int) $document->created_by === (int) $user->id) {
                return true;
            }

            if ($document->accesses()->where('user_id', $user->id)->exists()) {
                return true;
            }
        }

        foreach ($document->links()->with('linkable')->get() as $link) {
            $target = $link->linkable;

            if ($target instanceof Correspondence && $user->hasPermission('correspondence.view')) {
                return true;
            }

            if ($target instanceof Task && Gate::forUser($user)->allows('view', $target)) {
                return true;
            }

            if ($target instanceof Project && Gate::forUser($user)->allows('view', $target)) {
                return true;
            }

            if ($target instanceof ProjectSection && $target->project !== null && Gate::forUser($user)->allows('view', $target->project)) {
                return true;
            }
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->hasPermission('documents.create');
    }

    public function update(User $user, Document $document): bool
    {
        return $this->view($user, $document) && $user->hasPermission('documents.update');
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->view($user, $document)
            && $user->hasPermission('documents.delete')
            && (int) $document->created_by === (int) $user->id;
    }

    public function manageAccess(User $user, Document $document): bool
    {
        return $this->view($user, $document)
            && $user->hasPermission('documents.manage_access')
            && (int) $document->created_by === (int) $user->id;
    }
}
