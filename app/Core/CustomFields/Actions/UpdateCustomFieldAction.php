<?php

namespace App\Core\CustomFields\Actions;

use App\Core\CustomFields\Models\CustomField;

class UpdateCustomFieldAction
{
    public function execute(CustomField $field, array $data): CustomField
    {
        $data['options'] = $data['type'] === 'select' ? array_values($data['options'] ?? []) : null;
        $field->update($data);
        return $field->refresh();
    }
}
