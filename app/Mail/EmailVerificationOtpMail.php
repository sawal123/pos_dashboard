<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email delivery boundary for the AUTH-D02 OTP.
 *
 * This mailable deliberately does not implement ShouldQueue. The challenge is
 * committed only after synchronous delivery succeeds, and AUTH-D03 must not
 * report a usable challenge while an unguaranteed queue worker is unavailable.
 */
class EmailVerificationOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly CarbonInterface $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'POS Cloud email verification code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.verification-otp',
            with: [
                'code' => $this->code,
                'expiresAt' => $this->expiresAt,
            ],
        );
    }
}
