<?php

namespace App\Core\Departments\Actions;

use App\Core\Departments\Models\Department;
use App\Core\Departments\Models\Position;
use Illuminate\Validation\ValidationException;

class CreatePositionAction
{
    public function execute(Department $department, array $data): Position
    {
        if ($department->positions()->where('name', $data['name'])->exists()) {
            throw ValidationException::withMessages(['name' => 'این سمت در این واحد قبلاً تعریف شده است.']);
        }
        return $department->positions()->create([
            'name'=>$data['name'],'code'=>$data['code']??null,
            'description'=>$data['description']??null,'is_active'=>$data['is_active']??true,
        ]);
    }
}