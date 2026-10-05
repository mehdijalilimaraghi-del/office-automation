<?php

namespace App\Core\Departments\Actions;

use App\Core\Departments\Models\Position;
use App\Core\CustomFields\Services\CustomFieldService;
use Illuminate\Validation\ValidationException;

class UpdatePositionAction
{
    public function __construct(
        private readonly CustomFieldService $customFieldService,
    ) {
    }

    public function execute(Position $position, array $data): Position
    {
        if (Position::query()->where('department_id', $position->department_id)->where('name', $data['name'])->whereKeyNot($position->id)->exists()) {
            throw ValidationException::withMessages(['name' => 'این سمت در این واحد قبلاً تعریف شده است.']);
        }
        $position->update([
            'name' => $data['name'],
            'description'=>$data['description']??null,'is_active'=>$data['is_active']??true,
        ]);
        $this->customFieldService->sync($position, $data['custom_fields'] ?? []);

        return $position->refresh();
    }
}