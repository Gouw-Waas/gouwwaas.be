<?php

namespace App\Http\Controllers;

use App\Mail\ParticipantMessage;
use App\Models\Activity;
use App\Models\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/**
 * Organisers e-mailing everyone who registered (practical info, a change of plans…).
 * One e-mail per address, so participants don't see each other's addresses. Replies go to the
 * organiser who sent it.
 */
class ParticipantMailController extends Controller
{
    public function create(Activity $activity): View
    {
        Gate::authorize('viewRegistrations', $activity);

        return view('activiteiten.mail-deelnemers', [
            'activity' => $activity,
            'confirmedCount' => $this->recipients($activity, false)->count(),
            'allCount' => $this->recipients($activity, true)->count(),
        ]);
    }

    public function store(Request $request, Activity $activity): RedirectResponse
    {
        Gate::authorize('viewRegistrations', $activity);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:10000'],
            'include_unconfirmed' => ['nullable', 'boolean'],
            'send_copy' => ['nullable', 'boolean'],
        ], [], ['subject' => 'onderwerp', 'message' => 'bericht']);

        $recipients = $this->recipients($activity, $request->boolean('include_unconfirmed'));

        if ($recipients->isEmpty()) {
            return back()->withInput()->withErrors(['message' => 'Er is niemand om te mailen.']);
        }

        $sender = $request->user();
        // Sent straight away (no queue worker in production): give large groups the time they need.
        set_time_limit(max(120, $recipients->count() * 2));

        $failed = [];
        foreach ($recipients as $registration) {
            try {
                Mail::to($registration->email, $registration->name)
                    ->send(new ParticipantMessage($activity, $sender, $validated['subject'], $validated['message']));
            } catch (Throwable $e) {
                report($e);
                $failed[] = $registration->email;
            }
        }

        if ($request->boolean('send_copy')) {
            try {
                Mail::to($sender->email, $sender->name)
                    ->send(new ParticipantMessage($activity, $sender, $validated['subject'], $validated['message'], copy: true));
            } catch (Throwable $e) {
                report($e);
            }
        }

        $sent = $recipients->count() - count($failed);
        $redirect = redirect()->route('activiteiten.inschrijvingen', $activity)
            ->with('status', "Je bericht is verstuurd naar {$sent} ".($sent === 1 ? 'deelnemer' : 'deelnemers').'.');

        if ($failed) {
            $redirect->withErrors(['cancel' => 'Niet verstuurd naar: '.implode(', ', $failed).'. Probeer die later opnieuw of mail ze zelf.']);
        }

        return $redirect;
    }

    /**
     * Participants (optionally also those who never finished paying), one per e-mail address.
     *
     * @return Collection<int, Registration>
     */
    private function recipients(Activity $activity, bool $includeUnconfirmed): Collection
    {
        $query = $activity->registrations()->reorder('created_at');

        if (! $includeUnconfirmed) {
            $query->confirmed();
        }

        return $query->get()
            ->unique(fn (Registration $registration) => mb_strtolower(trim($registration->email)))
            ->values();
    }
}
