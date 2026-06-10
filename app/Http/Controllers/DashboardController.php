<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    public function index(): Response
    {
        return Inertia::render('dashboard', [
            'stats' => $this->dashboardService->stats(),
            'monthlyChart' => Inertia::defer(fn () => $this->dashboardService->monthlyChartData()),
            'sessionDistribution' => Inertia::defer(fn () => $this->dashboardService->sessionDistribution()),
            'sessionDurations' => Inertia::defer(fn () => $this->dashboardService->sessionDurationsForChart()),
        ]);
    }
}
