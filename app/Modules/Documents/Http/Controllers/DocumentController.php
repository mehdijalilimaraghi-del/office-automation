<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\Tasks\StoreTaskDocumentRequest;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Services\DocumentService;
use App\Modules\Tasks\Models\Task;
use App\Modules\Projects\Models\ProjectSection;
use App\Core\Permissions\Enums\PermissionScope;
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

    public function storeForProject(StoreTaskDocumentRequest $request, \App\Modules\Projects\Models\Project $project): RedirectResponse
    {
        $user = $request->user();
        $isProjectOwner = (int) $project->manager_id === (int) $user->id
            || (int) $project->created_by === (int) $user->id;
        $hasOrganizationScope = in_array(PermissionScope::Organization->value, $user->permissionScopes('projects.update'), true)
            || in_array(PermissionScope::Organization->value, $user->permissionScopes('projects.create'), true);

        abort_unless($isProjectOwner || $hasOrganizationScope, 403);
        Gate::authorize('create', Document::class);

        $this->documents->create(
            data: $request->validated(),
            file: $request->file('file'),
            creator: $user,
            linkable: $project,
            purpose: 'project_reference',
        );

        return redirect()->route('projects.show', $project)
            ->with('success', 'مدرک عمومی پروژه با موفقیت پیوست شد.');
    }

    public function storeForSection(StoreTaskDocumentRequest $request, ProjectSection $section): RedirectResponse
    {
        $section->load(['project', 'department']);
        $user = $request->user();

        $isProjectManager = (int) $section->project?->manager_id === (int) $user->id
            || (int) $section->project?->created_by === (int) $user->id;
        $hasOrganizationScope = in_array(PermissionScope::Organization->value, $user->permissionScopes('projects.update'), true)
            || in_array(PermissionScope::Organization->value, $user->permissionScopes('projects.create'), true);
        $isDepartmentManager = (int) $section->department?->manager_id === (int) $user->id;

        abort_unless($isProjectManager || $hasOrganizationScope || $isDepartmentManager, 403);
        Gate::authorize('create', Document::class);

        $this->documents->create(
            data: $request->validated(),
            file: $request->file('file'),
            creator: $user,
            linkable: $section,
            purpose: 'section_reference',
        );

        return redirect()->route('projects.show', $section->project_id)
            ->with('success', 'مدرک با موفقیت به بخش پروژه پیوست شد.');
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
