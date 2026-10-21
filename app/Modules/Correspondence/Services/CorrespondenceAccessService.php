<?php

declare(strict_types=1);

namespace App\\Modules\\Correspondence\\Services;

use App\\Models\\User;
use App\\Modules\\Correspondence\\Models\\Correspondence;
use Illuminate\\Database\\Eloquent\\Builder;

class CorrespondenceAccessService
{
    public function visibleToQuery(Builder $query, User $user): Builder
    {
        if (! $user->isActive() || ! $user->hasPermission('correspondence.view')) {
            return $query->whereRaw('1 = 0');
        }

        $isRegistrar = $user->hasPermission('correspondence.register');

        return $query->where(function (Builder $visible) use ($user, $isRegistrar): void {
            $visible->where(function (Builder $ordinary) use ($user): void {
                $ordinary->where('classification', Correspondence::CLASSIFICATION_ORDINARY)
                    ->where('status', Correspondence::STATUS_REGISTERED);
            });

            $visible->orWhere(function (Builder $private) use ($user, $isRegistrar): void {
                $private->where(function (Builder $classification): void {
                    $classification->where('classification', Correspondence::CLASSIFICATION_CONFIDENTIAL)
                        ->orWhere('status', Correspondence::STATUS_DRAFT);
                });

                if ($isRegistrar) {
                    return;
                }

                $private->where(function (Builder $related) use ($user): void {
                    $related->where('created_by', $user->id)
                        ->orWhere('registered_by', $user->id)
                        ->orWhereHas('authorizedUsers', fn (Builder $users) => $users->where('users.id', $user->id));
                });
            });
        });
    }

    public function canView(Correspondence $correspondence, User $user): bool
    {
        if (! $user->isActive() || ! $user->hasPermission('correspondence.view')) {
            return false;
        }

        if ($user->hasPermission('correspondence.register')) {
            return true;
        }

        if ($correspondence->status === Correspondence::STATUS_REGISTERED
            && $correspondence->classification === Correspondence::CLASSIFICATION_ORDINARY) {
            return true;
        }

        return (int) $correspondence->created_by === (int) $user->id
            || (int) $correspondence->registered_by === (int) $user->id
            || $correspondence->authorizedUsers()->where('users.id', $user->id)->exists();
    }
}
