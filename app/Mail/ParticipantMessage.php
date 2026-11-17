<?php

namespace App\Mail;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A message from an organiser to the participants of an activity. Replies go to the organiser.
 */
class ParticipantMessage extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  bool  $copy  the sender's own copy
     */
    public function __construct(
        public Activity $activity,
        public User $sender,
        public string $messageSubject,
        public string $body,
        public bool $copy = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->copy ? '[Kopie] ' : '').$this->messageSubject,
            replyTo: [new Address($this->sender->email, $this->sender->name)],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.bericht-deelnemers', with: [
            // Blank lines split paragraphs; single newlines stay line breaks.
            'paragraphs' => preg_split('/\R\s*\R/u', trim($this->body)) ?: [],
        ]);
    }
}
