<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\Tasks\StoreTaskDocumentRequest;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Services\DocumentService;
use App\Modules\Tasks\Models\Task;
use App\Modules\Projects\Models\ProjectSection;
use App\Modules\Projects\Models\Project;
use App\Core\Permissions\Enums\PermissionScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        // Only the current assignee can add files, and never while manager review is pending.
        Gate::authorize('update', $task);
        abort_if($task->isFinalized(), 403, 'پیوست دستورکار نهایی‌شده قابل تغییر نیست.');
        abort_if($task->review_status === 'pending', 403, 'پیوست دستورکار در زمان بررسی قابل تغییر نیست.');
        abort_unless((int) $task->assignee_id === (int) $request->user()->id, 403, 'فقط مسئول فعلی دستورکار می‌تواند پیوست اضافه کند.');

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

    public function destroyForTask(Request $request, Task $task, Document $document): RedirectResponse
    {
        Gate::authorize('update', $task);
        abort_if($task->isFinalized(), 403, 'پیوست دستورکار نهایی‌شده قابل تغییر نیست.');
        abort_if($task->review_status === 'pending', 403, 'پیوست دستورکار در زمان بررسی قابل حذف نیست.');
        abort_unless((int) $task->assignee_id === (int) $request->user()->id, 403, 'فقط مسئول فعلی دستورکار می‌تواند پیوست را حذف کند.');

        $isLinkedToTask = $document->links()
            ->where('linkable_type', Task::class)
            ->where('linkable_id', $task->id)
            ->exists();

        abort_unless($isLinkedToTask, 404);

        $this->documents->delete($document);

        return redirect()->route('tasks.show', $task)->with('success', 'پیوست با موفقیت حذف شد.');
    }

    public function storeForProject(StoreTaskDocumentRequest $request, \App\Modules\Projects\Models\Project $project): RedirectResponse
    {
        Gate::authorize('view', $project);
        $user = $request->user();
        $isProjectOwner = (int) $project->manager_id === (int) $user->id
            || (int) $project->created_by === (int) $user->id;
        $hasOrganizationScope = in_array(PermissionScope::Organization->value, $user->permissionScopes('projects.update'), true)
            || in_array(PermissionScope::Organization->value, $user->permissionScopes('projects.create'), true);

        // Project responsibility or organization-level project administration
        // authorizes attachments without requiring a separate global document grant.
        abort_unless($isProjectOwner || $hasOrganizationScope, 403);

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
        abort_if($section->project === null, 404);
        Gate::authorize('view', $section->project);
        $user = $request->user();

        $isProjectManager = (int) $section->project?->manager_id === (int) $user->id
            || (int) $section->project?->created_by === (int) $user->id;
        $hasOrganizationScope = in_array(PermissionScope::Organization->value, $user->permissionScopes('projects.update'), true)
            || in_array(PermissionScope::Organization->value, $user->permissionScopes('projects.create'), true);
        $isDepartmentManager = (int) $section->department?->manager_id === (int) $user->id;

        // Section responsibility is the authority boundary for section attachments.
        abort_unless($isProjectManager || $hasOrganizationScope || $isDepartmentManager, 403);

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

    public function destroy(Document $document): RedirectResponse
    {
        $document->load('links.linkable');

        $target = $document->links
            ->map(fn ($link) => $link->linkable)
            ->first(fn ($linkable) => $linkable instanceof Project || $linkable instanceof ProjectSection);

        abort_unless($target !== null, 404);

        $project = $target instanceof ProjectSection
            ? $target->load('project', 'department')->project
            : $target;

        abort_if($project === null, 404);
        Gate::authorize('view', $project);

        $user = request()->user();
        $hasOrganizationScope = in_array(PermissionScope::Organization->value, $user->permissionScopes('projects.update'), true)
            || in_array(PermissionScope::Organization->value, $user->permissionScopes('projects.create'), true);
        $isProjectOwner = (int) $project->manager_id === (int) $user->id
            || (int) $project->created_by === (int) $user->id;
        $isDepartmentManager = $target instanceof ProjectSection
            && (int) $target->department?->manager_id === (int) $user->id;

        abort_unless($isProjectOwner || $hasOrganizationScope || $isDepartmentManager, 403);
        Gate::authorize('delete', $document);

        $this->documents->delete($document);

        return redirect()->route('projects.show', $project)
            ->with('success', 'مدرک با موفقیت حذف شد.');
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        Gate::authorize('view', $document);
        $version = $document->latestVersion()->firstOrFail();
        $disk = Storage::disk($version->disk);
        abort_unless($disk->exists($version->path), 404);

        if ($request->boolean('inline') && in_array(strtolower((string) $version->mime_type), ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            $stream = $disk->readStream($version->path);
            abort_unless(is_resource($stream), 404);

            return response()->stream(function () use ($stream): void {
                fpassthru($stream);
                fclose($stream);
            }, 200, [
                'Content-Type' => $version->mime_type,
                'Content-Disposition' => 'inline; filename="' . addslashes(basename($version->original_name)) . '"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return $disk->download($version->path, $version->original_name, $version->mime_type !== null ? ['Content-Type' => $version->mime_type] : []);
    }
}
