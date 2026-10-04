<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Events;

use App\Modules\Tasks\Models\Task;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final class TaskApproved implements ShouldDispatchAfterCommit
{
    public function __construct(
        public readonly Task $task,
    ) {}
}
