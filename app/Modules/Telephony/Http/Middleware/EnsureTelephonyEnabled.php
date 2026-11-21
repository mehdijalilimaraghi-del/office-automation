<?php

declare(strict_types=1);

namespace App\Modules\Telephony\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTelephonyEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('telephony.enabled', false), 404);

        return $next($request);
    }
}
