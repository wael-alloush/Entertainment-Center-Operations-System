<?php

namespace App\Http\Middleware;

use App\Services\DeviceTrustService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignDeviceCookie
{
    public function __construct(private DeviceTrustService $deviceTrustService) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $existingToken = $request->cookie(DeviceTrustService::COOKIE_NAME);
        $needsCookie = ! is_string($existingToken) || ! Str::isUuid($existingToken);
        $token = null;

        if ($needsCookie) {
            $token = (string) Str::uuid();
            $request->cookies->set(DeviceTrustService::COOKIE_NAME, $token);
        }

        $response = $next($request);

        if ($request->user() !== null) {
            $this->deviceTrustService->touchDevice($request);
        }

        if ($needsCookie && $token !== null) {
            $response->headers->setCookie($this->deviceTrustService->attachDeviceCookie($token, $request));
        }

        return $response;
    }
}
