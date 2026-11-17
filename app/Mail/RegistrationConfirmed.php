<?php

namespace App\Mail;

use App\Models\Registration;
use App\Support\ActivityCalendar;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent once a registration is confirmed: straight away for free activities, after payment otherwise.
 * The activity comes along as a calendar file (.ics), with links for Google Calendar and Outlook.
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
            'calendar' => ActivityCalendar::for($this->registration->activity),
        ]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $calendar = ActivityCalendar::for($this->registration->activity);

        return [
            Attachment::fromData(fn () => $calendar->ics(), $calendar->filename())
                ->withMime('text/calendar; charset=utf-8; method=PUBLISH'),
        ];
    }
}
