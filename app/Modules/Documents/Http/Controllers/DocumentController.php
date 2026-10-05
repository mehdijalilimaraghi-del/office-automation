<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\Tasks\StoreTaskDocumentRequest;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Services\DocumentService;
use App\Modules\Tasks\Models\Task;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentService $documents,
    ) {
    }

    public function storeForTask(StoreTaskDocumentRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);
        Gate::authorize('create', Document::class);

        $data = $request->validated();

        $this->documents->create(
            data: $data,
            file: $request->file('file'),
            creator: $request->user(),
            linkable: $task,
            purpose: 'attachment',
        );

        return redirect()->route('tasks.show', $task)->with('success', 'فایل با موفقیت به تسک پیوست شد.');
    }

    public function download(Document $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        $version = $document->latestVersion()->firstOrFail();
        $disk = Storage::disk($version->disk);

        abort_unless($disk->exists($version->path), 404);

        return $disk->download(
            $version->path,
            $version->original_name,
            $version->mime_type !== null
                ? ['Content-Type' => $version->mime_type]
                : [],
        );
    }
}
