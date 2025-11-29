<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\LiftLog;

class FirstLiftOfTheDay extends Mailable
{
    use Queueable, SerializesModels;

    public LiftLog $liftLog;
    public string $environmentFile;

    /**
     * Create a new message instance.
     */
    public function __construct(LiftLog $liftLog, string $environmentFile)
    {
        $this->liftLog = $liftLog;
        $this->environmentFile = $environmentFile;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Hello ' . $this->liftLog->user->name . ', your First Lift Of The Day!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.first-lift-of-the-day',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
