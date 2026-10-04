<?php

namespace App\Core\CustomFields\Actions;

use App\Core\CustomFields\Models\CustomField;

class DeleteCustomFieldAction
{
    public function execute(CustomField $field): void
    {
        $field->delete();
    }
}
