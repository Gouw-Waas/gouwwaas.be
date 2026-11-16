<?php

namespace App\Payments;

use App\Mail\RegistrationConfirmed;
use App\Models\Registration;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Links registrations to payments and sends the confirmation e-mail, exactly once.
 */
class RegistrationCheckout
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /**
     * Start a payment for the registration; returns the checkout URL to send the payer to.
     *
     * @throws PaymentException
     */
    public function start(Registration $registration): string
    {
        // Still open (e.g. came back without finishing): continue that payment, so a payment
        // finished later in another tab still belongs to this registration.
        if ($registration->payment_status === 'open' && $registration->mollie_payment_id) {
            $url = $this->gateway->checkoutUrl($registration->mollie_payment_id);

            if ($url) {
                return $url;
            }
        }

        $activity = $registration->activity;

        $payment = $this->gateway->create(
            $registration->amount_cents,
            "Inschrijving {$activity->title} – {$registration->name}",
            route('inschrijvingen.show', $registration),
            $this->webhookUrl(),
            ['registration' => $registration->id],
        );

        $registration->forceFill([
            'mollie_payment_id' => $payment->id,
            'payment_status' => 'open',
        ])->save();

        return $payment->checkoutUrl;
    }

    /**
     * Fetch the latest status of the registration's payment (after the redirect back, or from the
     * webhook) and confirm the registration once it's paid.
     */
    public function refresh(Registration $registration): void
    {
        if ($registration->isFree() || ! $registration->mollie_payment_id || $registration->payment_status === 'paid') {
            return;
        }

        $status = $this->gateway->status($registration->mollie_payment_id);

        $registration->forceFill([
            'payment_status' => $status,
            'paid_at' => $status === 'paid' ? ($registration->paid_at ?? now()) : null,
        ])->save();

        if ($status === 'paid') {
            $this->sendConfirmation($registration);
        }
    }

    /**
     * Send the confirmation e-mail, unless it was sent already. Safe when the webhook and the
     * redirect page arrive at the same moment: only one of them claims the e-mail.
     */
    public function sendConfirmation(Registration $registration): void
    {
        if (! $registration->isConfirmed()) {
            return;
        }

        $claimed = Registration::whereKey($registration->getKey())
            ->whereNull('confirmation_sent_at')
            ->update(['confirmation_sent_at' => now()]);

        if (! $claimed) {
            return;
        }

        try {
            // Sent straight away: production has no queue worker.
            Mail::to($registration->email, $registration->name)->send(new RegistrationConfirmed($registration));
        } catch (Throwable $e) {
            // The registration itself is fine; release the claim so a later refresh can retry.
            Registration::whereKey($registration->getKey())->update(['confirmation_sent_at' => null]);
            report($e);

            return;
        }

        $registration->refresh();
    }

    /**
     * Mollie only calls back to public addresses; on a local machine the redirect page checks instead.
     */
    private function webhookUrl(): ?string
    {
        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true) || preg_match('/\.(test|local|localhost)$/', $host)) {
            return null;
        }

        return route('mollie.webhook');
    }
}
