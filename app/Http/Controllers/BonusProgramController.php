<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBonusProgramRequest;
use App\Services\BonusProgramService;
use Illuminate\Http\RedirectResponse;

class BonusProgramController extends Controller
{
    public function __construct(private BonusProgramService $bonusProgramService) {}

    public function update(UpdateBonusProgramRequest $request): RedirectResponse
    {
        $this->bonusProgramService->updateSettings($request->validated());

        return back()->with('success', 'تم تحديث إعدادات برنامج المكافآت.');
    }
}
