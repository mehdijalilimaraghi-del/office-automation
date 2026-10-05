<?php

namespace App\Core\CustomFields\Actions;

use App\Core\CustomFields\Models\CustomField;
use App\Core\CustomFields\Services\CustomFieldService;

class CreateCustomFieldAction
{
    public function __construct(
        private readonly CustomFieldService $customFieldService,
    ) {
    }

    public function execute(array $data): CustomField
    {
        $data['key'] = $this->customFieldService->generateKey($data['entity_type']);
        $data['options'] = $data['type'] === 'select' ? array_values($data['options'] ?? []) : null;
        return CustomField::create($data);
    }
}
