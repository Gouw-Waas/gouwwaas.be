<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Organisers marking who attended, on the registrations overview: one by one, or a selection
 * at once.
 */
class AttendanceController extends Controller
{
    public function update(Request $request, Activity $activity, Registration $registration): RedirectResponse|JsonResponse
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

        // Saved in the background by public/js/registrations.js, so filters and selection stay.
        if ($request->expectsJson()) {
            return response()->json([
                'present' => $registration->isPresent(),
                'presentCount' => $activity->registrations()->whereNotNull('present_at')->count(),
            ]);
        }

        // Without JavaScript: back to the same row, so ticking a long list doesn't jump to the top every time.
        return redirect()->to(route('activiteiten.inschrijvingen', $activity).'#inschrijving-'.$registration->id);
    }

    /**
     * The selection on the overview (ids[]): exactly those, present or not, confirmed or not,
     * since the organiser chose them.
     */
    public function markAll(Request $request, Activity $activity): RedirectResponse
    {
        Gate::authorize('viewRegistrations', $activity);

        $validated = $request->validate([
            'present' => ['required', 'boolean'],
            'ids' => ['required', 'array', 'max:1000'],
            'ids.*' => ['integer'],
        ]);
        $present = (bool) $validated['present'];

        $query = $activity->registrations()->whereKey($validated['ids']);
        $count = $present
            ? $query->whereNull('present_at')->update(['present_at' => now()])
            : $query->whereNotNull('present_at')->update(['present_at' => null]);

        return back()->with('status', "{$count} ".($count === 1 ? 'deelnemer' : 'deelnemers').' aangeduid als '.($present ? 'aanwezig.' : 'niet aanwezig.'));
    }
}
