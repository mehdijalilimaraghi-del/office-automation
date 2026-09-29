<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Actions;

use App\Modules\Tasks\Models\Task;
use Illuminate\Support\Facades\DB;

class DeleteTaskAction
{
    public function execute(Task $task): void
    {
        DB::transaction(function () use ($task): void {
            $task->delete();
        });
    }
}
