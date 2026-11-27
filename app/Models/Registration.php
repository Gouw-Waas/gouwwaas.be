<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property array<int, array{field_id: int, label: string, value: string|null}> $answers
 * @property Carbon|null $paid_at
 * @property Carbon|null $confirmation_sent_at
 * @property Carbon|null $present_at
 * @property Carbon|null $certificate_sent_at
 */
#[Fillable(['name', 'email', 'answers', 'amount_cents'])]
class Registration extends Model
{
    /** Statuses after which Mollie won't change the payment anymore (except paid). */
    public const FAILED_STATUSES = ['failed', 'canceled', 'expired'];

    /** A payment started at Mollie that may still come through: it holds a place. */
    public const IN_PROGRESS_STATUSES = ['open', 'pending', 'authorized'];

    public const SOURCE_MANUAL = 'manual';

    /** How long an unpaid online registration is kept before the nightly clean-up deletes it. */
    public const ABANDONED_AFTER_HOURS = 24;

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
            'paid_manually' => 'boolean',
            'confirmation_sent_at' => 'datetime',
            'present_at' => 'datetime',
            'certificate_sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Registration $registration) {
            $registration->token ??= Str::random(40);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * Free registrations, and paid ones whose payment came through.
     */
    /** @param Builder<Registration> $query */
    public function scopeConfirmed(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->where('amount_cents', 0)
            ->orWhere('payment_status', 'paid')
            ->orWhere('source', self::SOURCE_MANUAL));
    }

    /**
     * Registrations that take a place: confirmed ones, and payments in progress at Mollie
     * (so two people can't pay for the last place at the same time).
     *
     * @param  Builder<Registration>  $query
     */
    public function scopeTakingPlace(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->confirmed()
            ->orWhereIn('payment_status', self::IN_PROGRESS_STATUSES));
    }

    /**
     * Online registrations for a paid activity that will never be confirmed: no payment was
     * started, or Mollie ended it (failed, canceled, expired). Kept ABANDONED_AFTER_HOURS
     * after the last change, so the payer can still "Opnieuw proberen" from their page;
     * after that nobody comes back (they get no e-mail until they've paid).
     *
     * @param  Builder<Registration>  $query
     */
    public function scopeAbandoned(Builder $query): void
    {
        $query->unpaidOnline()->where('updated_at', '<', now()->subHours(self::ABANDONED_AFTER_HOURS));
    }

    /**
     * Online registrations for a paid activity with no payment, or a failed one: not on the
     * organisers' overview, and deleted once abandoned.
     *
     * @param  Builder<Registration>  $query
     */
    public function scopeUnpaidOnline(Builder $query): void
    {
        $query->where('amount_cents', '>', 0)
            ->where('source', '!=', self::SOURCE_MANUAL)
            ->where(fn (Builder $q) => $q
                ->whereNull('payment_status')
                ->orWhereIn('payment_status', self::FAILED_STATUSES));
    }

    /**
     * Payments in progress that haven't changed for ABANDONED_AFTER_HOURS: Mollie's webhook
     * may have been missed, so their status is checked again.
     *
     * @param  Builder<Registration>  $query
     */
    public function scopeStalePayment(Builder $query): void
    {
        $query->whereIn('payment_status', self::IN_PROGRESS_STATUSES)
            ->whereNotNull('mollie_payment_id')
            ->where('updated_at', '<', now()->subHours(self::ABANDONED_AFTER_HOURS));
    }

    public function isManual(): bool
    {
        return $this->source === self::SOURCE_MANUAL;
    }

    /**
     * Paid through Mollie, so it can be refunded through Mollie.
     */
    public function paidOnline(): bool
    {
        return $this->payment_status === 'paid' && ! $this->paid_manually && filled($this->mollie_payment_id);
    }

    /**
     * Organisers can mark it paid (cash, transfer…): not free, not paid, and no online payment in progress.
     */
    public function canBeMarkedPaid(): bool
    {
        return ! $this->isFree()
            && $this->payment_status !== 'paid'
            && ! in_array($this->payment_status, self::IN_PROGRESS_STATUSES, true);
    }

    public function isFree(): bool
    {
        return $this->amount_cents === 0;
    }

    /**
     * A participant: free, paid, or added by an organiser (who vouches for them, paid or not).
     */
    public function isConfirmed(): bool
    {
        return $this->isFree() || $this->payment_status === 'paid' || $this->isManual();
    }

    /**
     * Marked present by an organiser.
     */
    public function isPresent(): bool
    {
        return $this->present_at !== null;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function paymentFailed(): bool
    {
        return in_array($this->payment_status, self::FAILED_STATUSES, true);
    }

    /**
     * The payment is with the bank: paid, but not confirmed yet.
     */
    public function paymentProcessing(): bool
    {
        return in_array($this->payment_status, ['pending', 'authorized'], true);
    }

    public function amountLabel(): string
    {
        return '€ '.number_format($this->amount_cents / 100, 2, ',', '.');
    }

    /**
     * For the organisers' overview and the CSV.
     */
    public function paymentLabel(): string
    {
        if ($this->isFree()) {
            return 'Gratis';
        }

        if ($this->paid_manually) {
            return 'Betaald (manueel)';
        }

        if ($this->isManual() && $this->payment_status !== 'paid') {
            return 'Niet betaald (manueel)';
        }

        return match ($this->payment_status) {
            'paid' => 'Betaald',
            'pending', 'authorized' => 'In verwerking',
            'failed' => 'Mislukt',
            'canceled' => 'Geannuleerd',
            'expired' => 'Verlopen',
            default => 'Niet betaald',
        };
    }

    public function answerFor(int $fieldId): ?string
    {
        foreach ($this->answers as $answer) {
            if ($answer['field_id'] === $fieldId) {
                return $answer['value'];
            }
        }

        return null;
    }
}
