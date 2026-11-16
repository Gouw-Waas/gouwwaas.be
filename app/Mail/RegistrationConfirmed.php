<?php

namespace App\Mail;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent once a registration is confirmed: straight away for free activities, after payment otherwise.
 */
class RegistrationConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Registration $registration) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Je bent ingeschreven: {$this->registration->activity->title}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.inschrijving-bevestigd', with: [
            'activity' => $this->registration->activity,
        ]);
    }
}
