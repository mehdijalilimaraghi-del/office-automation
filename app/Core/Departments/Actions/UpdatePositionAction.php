<?php

namespace App\Core\Departments\Actions;

use App\Core\Departments\Models\Position;
use Illuminate\Validation\ValidationException;

class UpdatePositionAction
{
    public function execute(Position $position, array $data): Position
    {
        if (Position::query()->where('department_id', $position->department_id)->where('name', $data['name'])->whereKeyNot($position->id)->exists()) {
            throw ValidationException::withMessages(['name' => 'این سمت در این واحد قبلاً تعریف شده است.']);
        }
        $position->update([
            'name' => $data['name'],
            'description'=>$data['description']??null,'is_active'=>$data['is_active']??true,
        ]);
        return $position->refresh();
    }
}