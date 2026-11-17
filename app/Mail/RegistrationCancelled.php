<?php

namespace App\Mail;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when an organiser cancels a registration, or the whole activity.
 * Sent before the registration is removed (straight away, not queued).
 */
class RegistrationCancelled extends Mailable
{
    use Queueable;

    public function __construct(
        public Registration $registration,
        public int $refundedCents = 0,
        public int $adminCostCents = 0,
        public bool $activityCancelled = false,
    ) {}

    public function envelope(): Envelope
    {
        $title = $this->registration->activity->title;

        return new Envelope(subject: $this->activityCancelled
            ? "Gaat niet door: {$title}"
            : "Je inschrijving is geannuleerd: {$title}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.inschrijving-geannuleerd', with: [
            'activity' => $this->registration->activity,
            'refunded' => '€ '.number_format($this->refundedCents / 100, 2, ',', '.'),
            'adminCost' => '€ '.number_format($this->adminCostCents / 100, 2, ',', '.'),
        ]);
    }
}
