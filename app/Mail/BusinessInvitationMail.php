<?php

namespace App\Mail;

use App\Models\BusinessInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invitation email. Carries the one-time plaintext token to build the accept
 * link; the token is never persisted and the mail is sent after commit.
 */
class BusinessInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BusinessInvitation $invitation,
        public string $plainToken,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Undangan bergabung ke '.$this->invitation->business->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.business-invitation',
            with: [
                'acceptUrl' => route('invitations.show', ['token' => $this->plainToken]),
                'businessName' => $this->invitation->business->name,
                'expiresAt' => $this->invitation->expires_at,
                'role' => $this->invitation->roleLabel(),
            ],
        );
    }
}
