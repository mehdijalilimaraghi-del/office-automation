<?php

namespace App\Core\Departments\Actions;

use App\Core\Departments\Models\Position;

class DeletePositionAction
{
    public function execute(Position $position): void
    {
        $position->delete();
    }
}