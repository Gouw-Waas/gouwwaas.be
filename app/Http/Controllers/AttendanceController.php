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

        // Back to the same row, so ticking a long list doesn't jump to the top every time.
        return redirect()->to(route('activiteiten.inschrijvingen', $activity).'#inschrijving-'.$registration->id);
    }

    /**
     * Without a selection: everyone confirmed is present (unconfirmed registrations are only
     * ticked by hand). With a selection (ids[] from the overview): exactly those, present or
     * not, confirmed or not, since the organiser chose them.
     */
    public function markAll(Request $request, Activity $activity): RedirectResponse
    {
        Gate::authorize('viewRegistrations', $activity);

        $validated = $request->validate([
            'present' => ['nullable', 'boolean'],
            'ids' => ['nullable', 'array', 'max:1000'],
            'ids.*' => ['integer'],
        ]);
        $present = $request->boolean('present', true);

        if (isset($validated['ids'])) {
            $query = $activity->registrations()->whereKey($validated['ids']);
            $count = $present
                ? $query->whereNull('present_at')->update(['present_at' => now()])
                : $query->whereNotNull('present_at')->update(['present_at' => null]);
        } else {
            $count = $activity->registrations()->confirmed()->whereNull('present_at')->update(['present_at' => now()]);
        }

        return back()->with('status', "{$count} ".($count === 1 ? 'deelnemer' : 'deelnemers').' aangeduid als '.($present ? 'aanwezig.' : 'niet aanwezig.'));
    }
}
