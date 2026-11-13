<?php

namespace App\Http\Middleware;

use App\Services\BonusProgramService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBonusExpiryChecked
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            app(BonusProgramService::class)->expireOverdueBonusesIfNeeded();
        }

        return $next($request);
    }
}
