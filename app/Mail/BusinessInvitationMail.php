<?php

namespace App\Mail;

use App\Models\BusinessInvitation;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Invitation email. Carries the one-time plaintext token to build the accept
 * link.
 *
 * The message is queued so a delivery failure is retried instead of surfacing
 * as a 500, and it implements {@see ShouldBeEncrypted} so the queued payload
 * (including the plaintext token) is encrypted at rest in the queue and
 * failed-jobs tables. The token is never persisted anywhere in plaintext.
 */
class BusinessInvitationMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    /** Number of attempts before a delivery is recorded as failed. */
    public int $tries = 3;

    public function __construct(
        public BusinessInvitation $invitation,
        public string $plainToken,
    ) {}

    /**
     * Seconds to wait before each successive retry.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(12);
    }

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

    /**
     * Record a permanently failed delivery without leaking the token. The
     * invitation itself stays pending, so an owner can safely resend it.
     */
    public function failed(Throwable $exception): void
    {
        Log::warning('Business invitation email delivery failed after retries.', [
            'invitation_id' => $this->invitation->id,
            'business_id' => $this->invitation->business_id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
