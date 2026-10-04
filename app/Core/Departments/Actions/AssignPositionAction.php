<?php

namespace App\Core\Departments\Actions;

use App\Core\Departments\Models\Position;
use App\Core\Departments\Models\PositionAssignment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AssignPositionAction
{
    public function execute(Position $position, User $user, bool $primary = false): PositionAssignment
    {
        if (! $position->department()->whereHas('members', fn ($q) => $q->whereKey($user->id))->exists()) {
            throw ValidationException::withMessages(['user_id' => 'کاربر باید ابتدا عضو همین واحد سازمانی باشد.']);
        }
        if (! $position->is_active) {
            throw ValidationException::withMessages(['position' => 'سمت غیرفعال قابل تخصیص نیست.']);
        }

        $assignment = PositionAssignment::query()->updateOrCreate(
            ['position_id'=>$position->id,'user_id'=>$user->id],
            ['is_active'=>true,'is_primary'=>$primary,'started_at'=>now(),'ended_at'=>null]
        );

        if ($primary) {
            PositionAssignment::query()
                ->where('user_id', $user->id)
                ->whereKeyNot($assignment->id)
                ->update(['is_primary'=>false]);
        }

        return $assignment->refresh();
    }
}