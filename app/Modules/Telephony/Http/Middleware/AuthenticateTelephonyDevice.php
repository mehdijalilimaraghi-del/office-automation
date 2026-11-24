<?php

declare(strict_types=1);

namespace App\Modules\Telephony\Http\Middleware;

use App\Modules\Telephony\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTelephonyDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        abort_unless(is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token), 401);
        $device = Device::query()->where('status', 'linked')->where('token_hash', hash('sha256', $token))->first();
        abort_unless($device, 401);
        $request->attributes->set('telephony_device', $device);

        return $next($request);
    }
}
