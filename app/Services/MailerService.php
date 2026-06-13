<?php

namespace App\Services;

use App\Contracts\MailTransportInterface;
use App\Services\Mail\MailerSendApiTransport;
use App\Services\Mail\SmtpMailTransport;
use Illuminate\Mail\Mailable;
use RuntimeException;

/**
 * Entry point for sending email in the application.
 *
 * Resolves the correct transport driver based on the MAIL_TRANSPORT
 * environment variable and delegates delivery to it.
 *
 * Usage anywhere in the app:
 *
 *   app(\App\Services\MailerService::class)->send(
 *       to:       $person->email,
 *       mailable: new SomeMail($data),
 *       name:     $person->name,
 *   );
 *
 * Adding a new provider (Mailgun, Mandrill, Resend…):
 *   1. Create app/Services/Mail/XxxTransport.php implementing MailTransportInterface
 *   2. Add its credentials to config/services.php
 *   3. Add a 'case' in resolveTransport() below
 *   4. Set MAIL_TRANSPORT=xxx in .env — no other changes needed
 */
class MailerService
{
    private readonly MailTransportInterface $transport;

    public function __construct()
    {
        $this->transport = $this->resolveTransport();
    }

    /**
     * @param string $to Recipient email address.
     * @param Mailable $mailable Any fully constructed Laravel Mailable.
     * @param string|null $name Recipient display name (optional).
     *
     * @throws RuntimeException
     */
    public function send(string $to, Mailable $mailable, ?string $name = null): void
    {
        $this->transport->send($to, $name, $mailable);
    }

    private function resolveTransport(): MailTransportInterface
    {
        $driver = strtolower((string)config('services.mail_transport', 'smtp'));

        return match ($driver) {
            'mailersend' => new MailerSendApiTransport(
                apiToken: (string)config('services.mailersend.token'),
                fromAddress: (string)config('mail.from.address'),
                fromName: (string)config('mail.from.name'),
            ),
            // 'mailgun'  => new MailgunApiTransport(...),
            // 'mandrill' => new MandrillApiTransport(...),
            // 'resend'   => new ResendApiTransport(...),
            default => new SmtpMailTransport(),
        };
    }
}
