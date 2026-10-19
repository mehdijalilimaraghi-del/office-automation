<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Actions;

use App\Modules\Tasks\Models\Task;
use Illuminate\Support\Facades\DB;

class DeleteTaskAction
{
    public function execute(Task $task): void
    {
        if ($task->isFinalized()) {
            throw new \\InvalidArgumentException('دستورکار نهایی‌شده قابل حذف نیست.');
        }

        DB::transaction(function () use ($task): void {
            $task->delete();
        });
    }
}
