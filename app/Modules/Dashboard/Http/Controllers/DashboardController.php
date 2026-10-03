<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        return Inertia::render('Dashboard', $this->dashboardService->for($request->user()));
    }
}
