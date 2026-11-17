<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Registration;
use App\Payments\PaymentException;
use App\Payments\RegistrationCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Organisers adding someone themselves (signed up by phone, on paper…), and marking registrations
 * as paid when the money came in some other way (cash, bank transfer).
 *
 * Manual registrations count as participants straight away, paid or not: the organiser vouches for
 * them. They may go over the maximum number of participants; the form warns about it.
 */
class ManualRegistrationController extends Controller
{
    public function create(Activity $activity): View
    {
        Gate::authorize('viewRegistrations', $activity);

        return view('activiteiten.inschrijving-toevoegen', ['activity' => $activity->load('fields')]);
    }

    public function store(Request $request, Activity $activity, RegistrationCheckout $checkout): RedirectResponse
    {
        Gate::authorize('viewRegistrations', $activity);

        $details = $activity->validateRegistration($request, optional: true);
        $options = $request->validate([
            'paid' => [$activity->isFree() ? 'nullable' : 'required', 'boolean'],
            'send_confirmation' => ['nullable', 'boolean'],
        ], ['paid.required' => 'Kies of er al betaald is.']);

        $paid = ! $activity->isFree() && $request->boolean('paid');

        $registration = $activity->registrations()->make([
            ...$details,
            'amount_cents' => $activity->price_cents,
        ]);
        $registration->forceFill([
            'source' => Registration::SOURCE_MANUAL,
            'payment_status' => $paid ? 'paid' : null,
            'paid_manually' => $paid,
            'paid_at' => $paid ? now() : null,
        ])->save();

        $status = "{$registration->name} is ingeschreven.";

        if ($options['send_confirmation'] ?? false) {
            $checkout->sendConfirmation($registration);
            $status .= $registration->confirmation_sent_at
                ? " We stuurden een bevestiging naar {$registration->email}."
                : ' De bevestigingsmail kon niet verstuurd worden.';
        }

        return redirect()->route('activiteiten.inschrijvingen', $activity)->with('status', $status);
    }

    /**
     * The money came in outside the website: the registration counts as paid (and confirmed).
     */
    public function markPaid(Request $request, Activity $activity, Registration $registration, RegistrationCheckout $checkout): RedirectResponse
    {
        Gate::authorize('viewRegistrations', $activity);

        // A payment at Mollie might have come through (or expired) in the meantime.
        try {
            $checkout->refresh($registration);
        } catch (PaymentException $e) {
            report($e); // go on with what we know
        }

        if (! $registration->canBeMarkedPaid()) {
            return back()->withErrors(['cancel' => $registration->isPaid()
                ? "{$registration->name} heeft al betaald."
                : "De online betaling van {$registration->name} loopt nog. Wacht tot die afgerond of verlopen is."]);
        }

        $registration->forceFill([
            'payment_status' => 'paid',
            'paid_manually' => true,
            'paid_at' => now(),
        ])->save();

        $status = "De inschrijving van {$registration->name} staat op betaald.";

        if ($request->boolean('send_confirmation')) {
            $alreadySent = (bool) $registration->confirmation_sent_at;
            $checkout->sendConfirmation($registration);

            if (! $alreadySent && $registration->confirmation_sent_at) {
                $status .= " We stuurden een bevestiging naar {$registration->email}.";
            }
        }

        return back()->with('status', $status);
    }
}
