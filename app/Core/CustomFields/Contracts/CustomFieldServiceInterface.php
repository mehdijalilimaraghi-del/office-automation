<?php

namespace App\Core\CustomFields\Contracts;

use Illuminate\Database\Eloquent\Model;

interface CustomFieldServiceInterface
{
    public function sync(Model $entity, array $values): void;

    public function values(Model $entity): array;
}
