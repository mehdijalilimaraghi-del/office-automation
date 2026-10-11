<?php

namespace App\Core\Permissions\Http\Controllers;

use App\Core\Permissions\Services\PermissionService;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Core/Permissions/Index', [
            'permissions' => $this->permissionService->list(),
        ]);
    }
}
