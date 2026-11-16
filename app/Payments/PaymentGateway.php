<?php

namespace App\Payments;

/**
 * Online payments. Implemented by MolliePaymentGateway; tests use a fake.
 */
interface PaymentGateway
{
    /**
     * Start a payment and return where to send the payer.
     *
     * @param  array<string, scalar>  $metadata
     *
     * @throws PaymentException when the payment can't be started
     */
    public function create(int $amountCents, string $description, string $redirectUrl, ?string $webhookUrl, array $metadata = []): StartedPayment;

    /**
     * The current status of a payment: open, pending, authorized, paid, failed, canceled or expired.
     *
     * @throws PaymentException when the status can't be fetched
     */
    public function status(string $paymentId): string;

    /**
     * Where to continue a payment that is still open, or null when it can't be continued.
     *
     * @throws PaymentException
     */
    public function checkoutUrl(string $paymentId): ?string;
}
