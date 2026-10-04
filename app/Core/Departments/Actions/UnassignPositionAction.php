<?php

namespace App\Core\Departments\Actions;

use App\Core\Departments\Models\Position;
use App\Core\Departments\Models\PositionAssignment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UnassignPositionAction
{
    public function execute(Position $position, User $user): void
    {
        $assignment = PositionAssignment::query()
            ->where('position_id',$position->id)->where('user_id',$user->id)->first();
        if (! $assignment) return;

        if ($assignment->is_primary) {
            throw ValidationException::withMessages(['user_id' => 'سمت اصلی کاربر را ابتدا به سمت دیگری منتقل کنید.']);
        }
        $assignment->delete();
    }
}