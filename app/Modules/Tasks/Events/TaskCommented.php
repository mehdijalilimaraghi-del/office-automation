<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Events;

use App\Models\User;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskComment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class TaskCommented implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Task $task,
        public readonly TaskComment $comment,
        public readonly User $author,
    ) {
    }
}
