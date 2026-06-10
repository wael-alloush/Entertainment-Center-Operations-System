<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSessionDurationRequest;
use App\Http\Requests\UpdateSessionDurationRequest;
use App\Models\SessionDuration;
use Illuminate\Http\RedirectResponse;

class SessionDurationController extends Controller
{
    public function store(StoreSessionDurationRequest $request): RedirectResponse
    {
        SessionDuration::query()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        SessionDuration::flushCache();

        return back()->with('success', 'تمت إضافة نوع الجلسة.');
    }

    public function update(UpdateSessionDurationRequest $request, SessionDuration $sessionDuration): RedirectResponse
    {
        $sessionDuration->update($request->validated());

        SessionDuration::flushCache();

        return back()->with('success', 'تم تحديث نوع الجلسة.');
    }
}
