<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Services\CorrespondenceAccessService;
use App\Modules\Documents\Models\ArchiveReference;
use App\Modules\Documents\Models\Document;
use App\Modules\Tasks\Models\Task;
use App\Modules\Workflows\Models\WorkflowInstance;

class ArchiveReferenceService
{
    public function __construct(private readonly CorrespondenceAccessService $correspondence) {}

    public function add(Document $document, User $user, string $type, int $id): void
    {
        abort_unless($this->target($user, $type, $id) !== null, 404);

        $document->archiveReferences()->firstOrCreate(
            ['target_type' => $type, 'target_id' => $id],
            ['created_by' => $user->id],
        );
    }

    public function visible(Document $document, User $user): array
    {
        return $document->archiveReferences()->latest('id')->get()
            ->map(fn (ArchiveReference $reference) => $this->target($user, $reference->target_type, (int) $reference->target_id))
            ->filter()->values()->all();
    }

    private function target(User $user, string $type, int $id): ?array
    {
        $target = match ($type) {
            'correspondence' => Correspondence::query()->find($id),
            'task' => Task::query()->find($id),
            'workflow' => WorkflowInstance::query()->with('definition')->find($id),
            default => null,
        };

        return match (true) {
            $target instanceof Correspondence && $this->correspondence->canView($target, $user) =>
                ['type' => $type, 'id' => $id, 'title' => $target->subject, 'url' => route('correspondence.show', $target)],
            $target instanceof Task && $user->can('view', $target) =>
                ['type' => $type, 'id' => $id, 'title' => $target->title, 'url' => route('tasks.show', $target)],
            $target instanceof WorkflowInstance && (int) $target->initiated_by === (int) $user->id =>
                ['type' => $type, 'id' => $id, 'title' => $target->definition?->name, 'url' => null],
            default => null,
        };
    }
}
