<?php

namespace App\Payments;

use App\Mail\RegistrationCancelled;
use App\Models\Activity;
use App\Models\Registration;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Organisers cancelling a registration, or a whole activity: refund through Mollie,
 * tell the participant by e-mail, then remove the registration.
 *
 * The refund comes first: when it fails nothing is removed, so the organiser can try again.
 */
class RegistrationCancellation
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly RegistrationCheckout $checkout,
    ) {}

    /**
     * @param  int  $adminCostCents  kept back from the refund (only for paid registrations)
     * @param  bool  $activityCancelled  the whole activity is cancelled (other wording in the e-mail)
     * @return int the amount refunded, in cents
     *
     * @throws CancellationException when it can't be cancelled now (payment still at the bank)
     * @throws PaymentException when Mollie refuses the refund
     */
    public function cancel(Registration $registration, int $adminCostCents = 0, bool $activityCancelled = false): int
    {
        // A payment might have come through since the page was loaded.
        $this->checkout->refresh($registration);

        if ($registration->paymentProcessing()) {
            throw new CancellationException("De betaling van {$registration->name} wordt nog verwerkt door de bank. Probeer het later opnieuw.");
        }

        $refunded = 0;

        if ($registration->payment_status === 'paid') {
            $refunded = $registration->amount_cents - max(0, $adminCostCents);

            if ($refunded <= 0 || $refunded > $registration->amount_cents) {
                throw new CancellationException('De administratiekosten moeten lager zijn dan het betaalde bedrag.');
            }

            $this->gateway->refund(
                $registration->mollie_payment_id,
                $refunded,
                "Terugbetaling {$registration->activity->title} – {$registration->name}",
            );
        }

        // Only people who were really registered (free, or paid) get an e-mail.
        if ($registration->isConfirmed()) {
            try {
                Mail::to($registration->email, $registration->name)->send(
                    new RegistrationCancelled($registration, $refunded, $refunded ? $registration->amount_cents - $refunded : 0, $activityCancelled)
                );
            } catch (Throwable $e) {
                // The refund is done; a failing mail mustn't undo that.
                report($e);
            }
        }

        $registration->delete();

        return $refunded;
    }

    /**
     * Cancel every registration of the activity (paid ones refunded in full) and then delete it.
     * When some can't be cancelled, the activity stays with just those, so it can be retried.
     *
     * @return array{cancelled: int, refunded: int, failed: array<int, string>}
     */
    public function cancelActivity(Activity $activity): array
    {
        $result = ['cancelled' => 0, 'refunded' => 0, 'failed' => []];

        foreach ($activity->registrations()->get() as $registration) {
            try {
                $wasConfirmed = $registration->isConfirmed();
                $result['refunded'] += $this->cancel($registration, 0, activityCancelled: true);
                $result['cancelled'] += $wasConfirmed ? 1 : 0;
            } catch (CancellationException|PaymentException $e) {
                report($e);
                $result['failed'][] = $registration->name;
            }
        }

        if (! $result['failed']) {
            $activity->delete();
        }

        return $result;
    }
}
