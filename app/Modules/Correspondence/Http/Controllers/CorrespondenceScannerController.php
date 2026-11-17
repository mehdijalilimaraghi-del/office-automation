<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Http\Controllers;

use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Services\CorrespondenceAccessService;
use App\Modules\Documents\Services\ScannerStagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CorrespondenceScannerController
{
    public function __construct(private readonly ScannerStagingService $scanner) {}

    public function start(Request $request, CorrespondenceAccessService $access): JsonResponse
    {
        abort_unless($access->canCreateType(Correspondence::TYPE_INCOMING, $request->user()), 403);

        return $this->scanner->start($request, 'correspondence');
    }

    public function upload(Request $request, string $token): JsonResponse
    {
        return $this->scanner->upload($request, $token);
    }

    public function status(Request $request, string $token): JsonResponse
    {
        return $this->scanner->status($request, 'correspondence', $token);
    }

    public function preview(Request $request, string $token): BinaryFileResponse
    {
        return $this->scanner->preview($request, 'correspondence', $token);
    }
}
