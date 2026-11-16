<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Http\Controllers;

use App\Modules\Correspondence\Models\CorrespondenceBodyTemplate;
use App\Modules\Correspondence\Services\CorrespondenceBodySanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CorrespondenceBodyTemplateController
{
    public function index(Request $request): JsonResponse
    {
        $templates = CorrespondenceBodyTemplate::query()->where('user_id', $request->user()->id)
            ->orderBy('title')->orderBy('id')->get(['id', 'title']);

        return response()->json($templates);
    }

    public function show(Request $request, int $template): JsonResponse
    {
        $item = CorrespondenceBodyTemplate::query()->where('user_id', $request->user()->id)
            ->findOrFail($template, ['id', 'title', 'body']);

        return response()->json($item);
    }

    public function store(Request $request, CorrespondenceBodySanitizer $sanitizer): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:500000'],
        ]);

        $body = $sanitizer->sanitizeBody($data['body']);
        if (! $sanitizer->hasRichContent($body)) {
            throw ValidationException::withMessages(['body' => 'متن نامه برای ذخیره قالب خالی است.']);
        }

        $template = CorrespondenceBodyTemplate::query()->create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'body' => $body,
        ]);

        return response()->json($template->only(['id', 'title']), 201);
    }
}
