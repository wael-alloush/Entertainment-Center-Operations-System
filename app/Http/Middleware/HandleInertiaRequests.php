<?php

namespace App\Http\Middleware;

use App\Services\BonusProgramService;
use App\Services\InvoiceService;
use Closure;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            app(BonusProgramService::class)->expireOverdueBonusesIfNeeded();
        }

        return parent::handle($request, $next);
    }

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user()?->toInertiaArray(),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'generated_device_code' => $request->session()->get('generated_device_code'),
                'generated_device_code_expires_at' => $request->session()->get('generated_device_code_expires_at'),
            ],
            'pendingBonusCount' => fn (): int => $request->user() !== null
                ? app(InvoiceService::class)->pendingBonusCount()
                : 0,
            'pendingCreditCount' => fn (): int => $request->user() !== null
                ? app(InvoiceService::class)->pendingCreditCount()
                : 0,
            'eligibleBonusWithoutPendingCount' => fn (): int => $request->user()?->isAdmin()
                ? app(BonusProgramService::class)->eligibleCustomersWithoutPendingBonusCount()
                : 0,
        ]);
    }
}
