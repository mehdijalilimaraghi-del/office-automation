<?php

declare(strict_types=1);

namespace App\Core\Audit\Services;

use App\Core\Audit\Contracts\AuditServiceInterface;
use App\Core\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService implements AuditServiceInterface
{
    public function __construct()
    {
    }

    public function record(
        string $event,
        Model $auditable,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $metadata = [],
    ): void {
        $request = app()->bound('request') ? request() : null;

        AuditLog::create([
            'actor_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}