<?php

declare(strict_types=1);

namespace App\Modules\Cartable\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cartable\Services\CartableService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartableController extends Controller
{
    public function __construct(
        private readonly CartableService $cartable,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Cartable/Index', [
            'cartable' => $this->cartable->for($request->user()),
        ]);
    }
}
