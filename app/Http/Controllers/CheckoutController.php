<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Payments\PaymentException;
use App\Payments\RegistrationCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * A registration's own page (/inschrijvingen/{token}): the checkout for paid activities,
 * where Mollie sends the payer back to, and the confirmation. Plus Mollie's webhook.
 */
class CheckoutController extends Controller
{
    public function __construct(private readonly RegistrationCheckout $checkout) {}

    public function show(Registration $registration): View
    {
        $paymentError = false;

        // Back from Mollie (or reloading): ask Mollie for the latest status.
        try {
            $this->checkout->refresh($registration);
        } catch (PaymentException $e) {
            report($e);
            $paymentError = true;
        }

        return view('activiteiten.inschrijving', [
            'registration' => $registration,
            'activity' => $registration->activity->load('fields'),
            'paymentError' => $paymentError,
        ]);
    }

    public function pay(Registration $registration): RedirectResponse
    {
        if ($registration->isConfirmed()) {
            return redirect()->route('inschrijvingen.show', $registration);
        }

        if (! $registration->activity->isRegistrationOpen()) {
            return redirect()->route('inschrijvingen.show', $registration)
                ->withErrors(['payment' => 'De inschrijvingen voor deze activiteit zijn afgesloten.']);
        }

        try {
            // A payment still in progress at the bank could still come through: don't start a second one.
            $this->checkout->refresh($registration);
            if ($registration->isConfirmed() || $registration->paymentProcessing()) {
                return redirect()->route('inschrijvingen.show', $registration);
            }

            return redirect()->away($this->checkout->start($registration));
        } catch (PaymentException $e) {
            report($e);

            return redirect()->route('inschrijvingen.show', $registration)
                ->withErrors(['payment' => 'Het betalen kon niet gestart worden. Probeer het over enkele minuten opnieuw.']);
        }
    }

    /**
     * Mollie calls this with only a payment id when a payment changes; the status itself is
     * always fetched from Mollie, never taken from the request.
     */
    public function webhook(Request $request): Response
    {
        $registration = Registration::where('mollie_payment_id', (string) $request->input('id'))->first();

        if ($registration) {
            try {
                $this->checkout->refresh($registration);
            } catch (PaymentException $e) {
                report($e);

                return response('', 500); // Mollie retries later
            }
        }

        // Unknown ids get the same answer, so the webhook reveals nothing.
        return response('', 200);
    }
}
