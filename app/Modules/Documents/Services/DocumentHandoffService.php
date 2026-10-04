<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Models\User;
use App\Modules\Documents\Models\DocumentLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class DocumentHandoffService
{
    public function __construct(
        private readonly DocumentService $documents,
    ) {}

    public function handoff(
        Model $source,
        Model $destination,
        User $actor,
        array $sourcePurposes = ['task_output'],
        string $destinationPurpose = 'handoff',
    ): int {
        $links = DocumentLink::query()
            ->where('linkable_type', $source->getMorphClass())
            ->where('linkable_id', $source->getKey())
            ->whereIn('purpose', $sourcePurposes)
            ->with('document')
            ->get();

        foreach ($links as $link) {
            $this->documents->link(
                $link->document,
                $destination,
                $actor,
                $destinationPurpose,
            );
        }

        return $links->count();
    }
}
