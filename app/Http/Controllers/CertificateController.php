<?php

namespace App\Http\Controllers;

use App\Certificates\CertificateRenderer;
use App\Certificates\CertificateUnavailable;
use App\Mail\CertificateMail;
use App\Models\Activity;
use App\Models\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Participation certificates (attesten) for activities with vormingsuren, for registrants
 * who are present and confirmed.
 */
class CertificateController extends Controller
{
    public function download(Activity $activity, Registration $registration, CertificateRenderer $renderer): Response|RedirectResponse
    {
        Gate::authorize('viewRegistrations', $activity);

        abort_unless($activity->hasCertificates() && $registration->isPresent() && $registration->isConfirmed(), 404);

        try {
            $renderer->ensureAvailable($activity);
        } catch (CertificateUnavailable $e) {
            return back()->withErrors(['certificate' => $e->getMessage()]);
        }

        return response($renderer->pdf($registration), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$renderer->filename($registration).'"',
        ]);
    }

    /**
     * E-mail every present, confirmed registrant who hasn't had their certificate yet: one mail
     * per registration (siblings sharing an address each get their own).
     */
    public function send(Request $request, Activity $activity, CertificateRenderer $renderer): RedirectResponse
    {
        Gate::authorize('viewRegistrations', $activity);

        // Optional selection from the overview: only these (if they qualify).
        $ids = $request->validate(['ids' => ['nullable', 'array', 'max:1000'], 'ids.*' => ['integer']])['ids'] ?? null;

        try {
            $renderer->ensureAvailable($activity);
        } catch (CertificateUnavailable $e) {
            return back()->withErrors(['certificate' => $e->getMessage()]);
        }

        if ($activity->starts_at->isFuture()) {
            return back()->withErrors(['certificate' => 'Attesten kun je pas versturen vanaf de start van de activiteit.']);
        }

        $recipients = $activity->certificateRecipients($ids);

        if ($recipients->isEmpty()) {
            return back()->withErrors(['certificate' => $ids === null
                ? 'Er zijn geen nieuwe attesten om te versturen.'
                : 'Geen van de geselecteerde deelnemers kan nog een attest krijgen.']);
        }

        // Sent straight away (no queue worker in production): give large groups the time they need.
        set_time_limit(max(120, $recipients->count() * 2));

        $sent = 0;
        $failed = [];
        foreach ($recipients as $registration) {
            // Claim it first: a second click while this batch is still running then skips
            // everyone this request already has (or is about to) mail.
            $claimed = Registration::whereKey($registration->id)->whereNull('certificate_sent_at')
                ->update(['certificate_sent_at' => now()]);
            if (! $claimed) {
                continue;
            }

            try {
                Mail::to($registration->email, $registration->name)
                    ->send(new CertificateMail($registration, $renderer->pdf($registration), $renderer->filename($registration)));
                $sent++;
            } catch (Throwable $e) {
                report($e);
                Registration::whereKey($registration->id)->update(['certificate_sent_at' => null]);
                $failed[] = $registration->name;
            }
        }

        $redirect = redirect()->route('activiteiten.inschrijvingen', $activity)
            ->with('status', $sent === 1 ? '1 attest verstuurd.' : "{$sent} attesten verstuurd.");

        if ($failed) {
            $redirect->withErrors(['certificate' => 'Niet verstuurd naar: '.implode(', ', $failed).'. Probeer later opnieuw: wie al een attest kreeg, krijgt het niet nog eens.']);
        }

        return $redirect;
    }
}
