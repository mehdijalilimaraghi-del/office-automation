<?php

namespace App\Core\Departments\Actions;

use App\Core\Departments\Models\Department;
use App\Core\Departments\Models\Position;
use App\Core\CustomFields\Services\CustomFieldService;
use Illuminate\Validation\ValidationException;

class CreatePositionAction
{
    public function __construct(
        private readonly CustomFieldService $customFieldService,
    ) {
    }

    public function execute(Department $department, array $data): Position
    {
        if ($department->positions()->where('name', $data['name'])->exists()) {
            throw ValidationException::withMessages(['name' => 'این سمت در این واحد قبلاً تعریف شده است.']);
        }
        $position = $department->positions()->create([
            'name' => $data['name'],
            'description'=>$data['description']??null,'is_active'=>$data['is_active']??true,
        ]);

        $this->customFieldService->sync($position, $data['custom_fields'] ?? []);

        return $position->refresh();
    }
}