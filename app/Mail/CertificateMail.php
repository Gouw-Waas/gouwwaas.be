<?php

namespace App\Mail;

use App\Models\Organisation;
use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A participant's participation certificate (attest), as PDF attachment.
 */
class CertificateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Registration $registration,
        public string $pdf,
        public string $filename,
    ) {}

    public function envelope(): Envelope
    {
        $organisation = Organisation::current();

        return new Envelope(
            subject: 'Deelnameattest: '.$this->registration->activity->title,
            replyTo: [new Address($organisation->email, $organisation->name)],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.attest', with: ['activity' => $this->registration->activity]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf, $this->filename)->withMime('application/pdf')];
    }
}
