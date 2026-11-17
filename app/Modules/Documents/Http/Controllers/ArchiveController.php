<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers;

use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Services\DocumentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ArchiveController
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Document::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::in(['digital', 'physical'])],
        ]);

        $userId = $request->user()->id;
        $records = Document::query()
            ->select(['id', 'title', 'document_type', 'archive_kind', 'archive_location', 'retain_until'])
            ->where('is_active', true)
            ->whereNotNull('archive_kind')
            ->where(function (Builder $query) use ($userId): void {
                $query->where('created_by', $userId)
                    ->orWhereHas('accesses', fn (Builder $access) => $access->where('user_id', $userId));
            })
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $pattern = '%'.str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $filters['search']).'%';
                $query->where(function (Builder $query) use ($pattern): void {
                    $query->where('title', 'like', $pattern)
                        ->orWhere('document_type', 'like', $pattern)
                        ->orWhere('description', 'like', $pattern);
                });
            })
            ->when($filters['kind'] ?? null, fn (Builder $query, string $kind) => $query->where('archive_kind', $kind))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Archive/Index', [
            'records' => $records,
            'filters' => $filters,
            'canCreate' => Gate::allows('create', Document::class),
        ]);
    }

    public function store(Request $request, DocumentService $documents): RedirectResponse
    {
        Gate::authorize('viewAny', Document::class);
        Gate::authorize('create', Document::class);

        $fileTypes = $request->input('archive_kind') === 'physical'
            ? 'mimes:pdf,jpg,jpeg,png,tif,tiff'
            : 'mimes:pdf,jpg,jpeg,png,tif,tiff,doc,docx,xls,xlsx';

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'archive_kind' => ['required', Rule::in(['digital', 'physical'])],
            'archive_location' => ['exclude_unless:archive_kind,physical', 'required', 'string', 'max:255'],
            'retain_until' => ['nullable', 'date_format:Y-m-d'],
            'file' => ['required', 'file', 'max:20480', $fileTypes],
        ]);

        $documents->create($data, $request->file('file'), $request->user());

        return redirect()->route('archive.index')->with('success', 'مدرک در آرشیو ثبت شد.');
    }
}
