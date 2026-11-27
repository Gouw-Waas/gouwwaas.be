<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Organisers marking who attended, on the registrations overview: one by one, or everyone
 * (confirmed) at once afterwards.
 */
class AttendanceController extends Controller
{
    public function update(Request $request, Activity $activity, Registration $registration): RedirectResponse
    {
        Gate::authorize('viewRegistrations', $activity);

        $present = $request->validate(['present' => ['required', 'boolean']])['present'];

        if ((bool) $present) {
            // Keep the first time it was marked.
            $registration->present_at ??= now();
        } else {
            $registration->present_at = null;
        }
        $registration->save();

        return back();
    }

    /**
     * Everyone confirmed is present; unconfirmed registrations (payment not finished) are only
     * ticked by hand.
     */
    public function markAll(Activity $activity): RedirectResponse
    {
        Gate::authorize('viewRegistrations', $activity);

        $count = $activity->registrations()->confirmed()->whereNull('present_at')->update(['present_at' => now()]);

        return back()->with('status', "{$count} ".($count === 1 ? 'deelnemer' : 'deelnemers').' aangeduid als aanwezig.');
    }
}
