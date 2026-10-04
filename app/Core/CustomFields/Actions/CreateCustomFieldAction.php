<?php

namespace App\Core\CustomFields\Actions;

use App\Core\CustomFields\Models\CustomField;
use App\Core\CustomFields\Services\CustomFieldService;

class CreateCustomFieldAction
{
    public function execute(array $data): CustomField
    {
        $data['options'] = $data['type'] === 'select' ? array_values($data['options'] ?? []) : null;
        return CustomField::create($data);
    }
}
