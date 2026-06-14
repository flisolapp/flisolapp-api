<?php

namespace App\Services\Mail;

use App\Contracts\MailTransportInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Sends email through Laravel's built-in mail system (SMTP, log, array…).
 *
 * Useful for local development (MAIL_MAILER=log) and environments
 * where SMTP is reachable.
 *
 * .env:
 *   MAIL_TRANSPORT=smtp
 *   MAIL_MAILER=log        # local dev
 *   MAIL_MAILER=smtp       # production SMTP relay
 */
class SmtpMailTransport implements MailTransportInterface
{
    public function send(string $to, ?string $name, Mailable $mailable): void
    {
        try {
            Mail::to(
                $name
                    ? new Address($to, $name)
                    : $to
            )->send($mailable);

            Log::info('SmtpMailTransport: sent', [
                'to' => $to,
                'name' => $name,
                'subject' => $mailable->envelope()->subject ?? '(no subject)',
            ]);
        } catch (Throwable $e) {
            Log::error('SmtpMailTransport: failed', [
                'to' => $to,
                'name' => $name,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                "SMTP transport failed for <{$to}>: {$e->getMessage()}",
                previous: $e,
            );
        }
    }
}
