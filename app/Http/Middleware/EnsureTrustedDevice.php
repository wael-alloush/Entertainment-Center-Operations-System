<?php

namespace App\Http\Middleware;

use App\Services\DeviceTrustService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTrustedDevice
{
    public function __construct(private DeviceTrustService $deviceTrustService) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->deviceTrustService->isEnabled()) {
            return $next($request);
        }

        if ($request->user()?->isAdmin()) {
            return $next($request);
        }

        if ($this->deviceTrustService->isDeviceTrusted($request)) {
            $this->deviceTrustService->touchDevice($request);

            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'هذا الجهاز غير موثّق.');
        }

        return redirect()
            ->route('device.register');
    }
}
