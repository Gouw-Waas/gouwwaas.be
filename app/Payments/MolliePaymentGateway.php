<?php

namespace App\Payments;

use Mollie\Api\Exceptions\MollieException;
use Mollie\Api\MollieApiClient;

/**
 * Payments through Mollie (mollie/mollie-api-php). A test key (test_…) gives test payments,
 * where Mollie's checkout lets you choose the outcome (paid, failed, canceled, …).
 */
class MolliePaymentGateway implements PaymentGateway
{
    private ?MollieApiClient $client = null;

    public function __construct(private readonly ?string $apiKey) {}

    public function create(int $amountCents, string $description, string $redirectUrl, ?string $webhookUrl, array $metadata = []): StartedPayment
    {
        try {
            $payment = $this->client()->payments->create(array_filter([
                'amount' => ['currency' => 'EUR', 'value' => number_format($amountCents / 100, 2, '.', '')],
                'description' => mb_substr($description, 0, 255),
                'redirectUrl' => $redirectUrl,
                // Mollie can't reach a local machine; the redirect page checks the status itself.
                'webhookUrl' => $webhookUrl,
                'metadata' => $metadata,
                'locale' => 'nl_BE',
            ], fn ($value) => $value !== null));
        } catch (MollieException $e) {
            throw new PaymentException('Mollie: '.$e->getMessage(), previous: $e);
        }

        $checkoutUrl = $payment->getCheckoutUrl();

        if (! $checkoutUrl) {
            throw new PaymentException("Mollie gave no checkout URL for payment {$payment->id}.");
        }

        return new StartedPayment($payment->id, $checkoutUrl);
    }

    public function status(string $paymentId): string
    {
        try {
            $payment = $this->client()->payments->get($paymentId);
        } catch (MollieException $e) {
            throw new PaymentException('Mollie: '.$e->getMessage(), previous: $e);
        }

        return match (true) {
            $payment->isPaid() => 'paid',
            $payment->isAuthorized() => 'authorized',
            $payment->isPending() => 'pending',
            $payment->isFailed() => 'failed',
            $payment->isCanceled() => 'canceled',
            $payment->isExpired() => 'expired',
            default => 'open',
        };
    }

    public function checkoutUrl(string $paymentId): ?string
    {
        try {
            $payment = $this->client()->payments->get($paymentId);
        } catch (MollieException $e) {
            throw new PaymentException('Mollie: '.$e->getMessage(), previous: $e);
        }

        return $payment->isOpen() ? $payment->getCheckoutUrl() : null;
    }

    public function refund(string $paymentId, int $amountCents, string $description): string
    {
        try {
            $refund = $this->client()->paymentRefunds->createForId($paymentId, [
                'amount' => ['currency' => 'EUR', 'value' => number_format($amountCents / 100, 2, '.', '')],
                'description' => mb_substr($description, 0, 140),
            ]);
        } catch (MollieException $e) {
            throw new PaymentException('Mollie: '.$e->getMessage(), previous: $e);
        }

        return $refund->id;
    }

    private function client(): MollieApiClient
    {
        if (! $this->apiKey) {
            throw new PaymentException('No Mollie API key configured (MOLLIE_API_KEY).');
        }

        return $this->client ??= (new MollieApiClient)->setApiKey($this->apiKey);
    }
}
