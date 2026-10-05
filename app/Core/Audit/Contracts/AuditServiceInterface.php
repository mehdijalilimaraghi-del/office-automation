<?php

declare(strict_types=1);

namespace App\Core\Audit\Contracts;

use Illuminate\Database\Eloquent\Model;

interface AuditServiceInterface
{
    public function record(
        string $event,
        Model $auditable,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $metadata = [],
    ): void;
}