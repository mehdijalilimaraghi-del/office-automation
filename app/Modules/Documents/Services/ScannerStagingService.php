<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ScannerStagingService
{
    private static function cacheKey(string $purpose, string $token): string
    {
        return $purpose.'_scanner_upload:'.hash('sha256', $token);
    }

    public function start(Request $request, string $purpose): JsonResponse
    {
        $this->removeExpiredStagingFiles();

        $token = Str::random(64);
        Cache::put(self::cacheKey($purpose, $token), [
            'user_id' => (int) $request->user()->id,
            'status' => 'pending',
            'created_at' => now()->toIso8601String(),
        ], now()->addMinutes(30));

        return response()->json([
            'token' => $token,
            'upload_url' => route('correspondence.scanner.upload', ['token' => $token]),
            'status_url' => route($purpose.'.scanner.status', ['token' => $token]),
        ]);
    }

    public function upload(Request $request, string $token): JsonResponse
    {
        // Both forms use the callback already accepted by installed Windows helpers.
        $key = self::cacheKey('correspondence', $token);
        $state = Cache::get($key);
        if ($state === null) {
            $key = self::cacheKey('archive', $token);
            $state = Cache::get($key);
        }

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
        ], now()->addMinutes(30));

        return response()->json(['status' => 'ready']);
    }

    public function status(Request $request, string $purpose, string $token): JsonResponse
    {
        $state = Cache::get(self::cacheKey($purpose, $token));
        abort_unless(is_array($state) && (int) ($state['user_id'] ?? 0) === (int) $request->user()->id, 404);
        return response()->json([
            'status' => $state['status'] ?? 'pending',
            'file_name' => ($state['status'] ?? null) === 'ready' ? ($state['original_name'] ?? 'scanned-letter.pdf') : null,
            'preview_url' => ($state['status'] ?? null) === 'ready' ? route($purpose.'.scanner.preview', ['token' => $token]) : null,
        ]);
    }

    public function preview(Request $request, string $purpose, string $token): BinaryFileResponse
    {
        $state = Cache::get(self::cacheKey($purpose, $token));
        abort_unless(is_array($state) && ($state['status'] ?? null) === 'ready'
            && (int) ($state['user_id'] ?? 0) === (int) $request->user()->id
            && is_string($state['path'] ?? null)
            && Storage::disk('local')->exists($state['path']), 404);
        return response()->file(Storage::disk('local')->path($state['path']), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="scanned-letter.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
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

    public function discard(array $upload): void
    {
        Cache::forget($upload['key']);
        Storage::disk('local')->delete($upload['path']);
    }

    public function readyUpload(string $purpose, string $token, User $user): ?array
    {
        $key = self::cacheKey($purpose, $token);
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
