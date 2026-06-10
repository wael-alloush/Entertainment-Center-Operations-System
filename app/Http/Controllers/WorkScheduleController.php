<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateWorkScheduleRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;

class WorkScheduleController extends Controller
{
    public function update(UpdateWorkScheduleRequest $request): RedirectResponse
    {
        Setting::setValue('work_schedule', [
            'start_time' => $request->validated('start_time'),
        ]);

        return back()->with('success', 'تم تحديث وقت بدء الدوام.');
    }
}
