<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'answers', 'amount_cents'])]
class Registration extends Model
{
    /** Statuses after which Mollie won't change the payment anymore (except paid). */
    public const FAILED_STATUSES = ['failed', 'canceled', 'expired'];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
            'confirmation_sent_at' => 'datetime',
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

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * Free registrations, and paid ones whose payment came through.
     */
    public function scopeConfirmed(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->where('amount_cents', 0)->orWhere('payment_status', 'paid'));
    }

    public function isFree(): bool
    {
        return $this->amount_cents === 0;
    }

    public function isConfirmed(): bool
    {
        return $this->isFree() || $this->payment_status === 'paid';
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
