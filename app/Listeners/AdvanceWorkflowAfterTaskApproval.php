<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Modules\Tasks\Events\TaskApproved;
use App\Modules\Workflows\Services\WorkflowService;

final class AdvanceWorkflowAfterTaskApproval
{
    public function __construct(
        private readonly WorkflowService $workflowService,
    ) {}

    public function handle(TaskApproved $event): void
    {
        $this->workflowService->advanceAfterTaskApproval($event->task);
    }
}
