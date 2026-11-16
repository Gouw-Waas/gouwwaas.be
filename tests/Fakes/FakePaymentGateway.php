<?php

namespace Tests\Fakes;

use App\Payments\PaymentException;
use App\Payments\PaymentGateway;
use App\Payments\StartedPayment;

/**
 * Stands in for Mollie in tests: records the payments started and returns the status set by the test.
 */
class FakePaymentGateway implements PaymentGateway
{
    /** @var array<int, array{id: string, amount: int, description: string, redirectUrl: string, webhookUrl: ?string, metadata: array}> */
    public array $created = [];

    /** @var array<string, string> payment id => status */
    public array $statuses = [];

    public bool $failing = false;

    public function create(int $amountCents, string $description, string $redirectUrl, ?string $webhookUrl, array $metadata = []): StartedPayment
    {
        $this->failIfNeeded();

        $id = 'tr_test'.(count($this->created) + 1);
        $this->created[] = ['id' => $id, 'amount' => $amountCents, 'description' => $description, 'redirectUrl' => $redirectUrl, 'webhookUrl' => $webhookUrl, 'metadata' => $metadata];
        $this->statuses[$id] = 'open';

        return new StartedPayment($id, "https://mollie.test/checkout/{$id}");
    }

    public function status(string $paymentId): string
    {
        $this->failIfNeeded();

        return $this->statuses[$paymentId] ?? throw new PaymentException("Unknown payment {$paymentId}");
    }

    public function checkoutUrl(string $paymentId): ?string
    {
        $this->failIfNeeded();

        return ($this->statuses[$paymentId] ?? null) === 'open' ? "https://mollie.test/checkout/{$paymentId}" : null;
    }

    private function failIfNeeded(): void
    {
        if ($this->failing) {
            throw new PaymentException('Mollie is unreachable (fake).');
        }
    }
}
