<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Http\Controllers;

use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Services\CorrespondenceAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CorrespondenceScannerController
{
    private function cacheKey(string $token): string
    {
        return 'correspondence_scanner_upload:'.hash('sha256', $token);
    }

    public function start(Request $request, CorrespondenceAccessService $access): JsonResponse
    {
        abort_unless($access->canCreateType(Correspondence::TYPE_INCOMING, $request->user()), 403);

        $this->removeExpiredStagingFiles();

        $token = Str::random(64);
        Cache::put($this->cacheKey($token), [
            'user_id' => (int) $request->user()->id,
            'status' => 'pending',
            'created_at' => now()->toIso8601String(),
        ], now()->addMinutes(10));

        return response()->json([
            'token' => $token,
            'upload_url' => route('correspondence.scanner.upload', ['token' => $token]),
            'status_url' => route('correspondence.scanner.status', ['token' => $token]),
        ]);
    }

    public function upload(Request $request, string $token): JsonResponse
    {
        $key = $this->cacheKey($token);
        $state = Cache::get($key);

        abort_unless(is_array($state) && ($state['status'] ?? null) === 'pending', 404);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf'],
        ]);

        $directory = 'scanner-staging/'.(int) $state['user_id'];
        $filename = hash('sha256', $token).'.pdf';
        $path = Storage::disk('local')->putFileAs($directory, $data['file'], $filename);

        if (! is_string($path) || $path === '') {
            return response()->json(['message' => 'ذخیره فایل اسکن‌شده انجام نشد.'], 500);
        }

        Cache::put($key, [
            ...$state,
            'status' => 'ready',
            'path' => $path,
            'original_name' => Str::limit($data['file']->getClientOriginalName(), 255, ''),
        ], now()->addMinutes(10));

        return response()->json(['status' => 'ready']);
    }

    public function status(Request $request, string $token): JsonResponse
    {
        $state = Cache::get($this->cacheKey($token));

        abort_unless(is_array($state) && (int) ($state['user_id'] ?? 0) === (int) $request->user()->id, 404);

        return response()->json([
            'status' => $state['status'] ?? 'pending',
            'file_name' => $state['status'] === 'ready' ? ($state['original_name'] ?? 'scanned-letter.pdf') : null,
        ]);
    }

    private function removeExpiredStagingFiles(): void
    {
        foreach (Storage::disk('local')->allFiles('scanner-staging') as $path) {
            try {
                if (Storage::disk('local')->lastModified($path) < now()->subMinutes(30)->timestamp) {
                    Storage::disk('local')->delete($path);
                }
            } catch (\Throwable) {
                // Cleanup is best-effort; it must not prevent a new scan.
            }
        }
    }

    public static function consume(string $token, User $user): ?array
    {
        $key = 'correspondence_scanner_upload:'.hash('sha256', $token);
        $state = Cache::get($key);

        if (! is_array($state)
            || ($state['status'] ?? null) !== 'ready'
            || (int) ($state['user_id'] ?? 0) !== (int) $user->id
            || ! is_string($state['path'] ?? null)
            || ! Storage::disk('local')->exists($state['path'])) {
            return null;
        }

        return ['key' => $key, ...$state];
    }
}
