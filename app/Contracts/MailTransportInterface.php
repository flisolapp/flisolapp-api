<?php

namespace App\Contracts;

use Illuminate\Mail\Mailable;
use RuntimeException;

/**
 * Contract for all mail transport drivers.
 *
 * To add a new provider (Mailgun, Mandrill, Resend…):
 *   1. Implement this interface in app/Services/Mail/
 *   2. Add its credentials in config/services.php
 *   3. Add a 'case' in MailerService::resolveTransport()
 */
interface MailTransportInterface
{
    /**
     * @param string $to Recipient email address.
     * @param string|null $name Recipient display name.
     * @param Mailable $mailable Any Laravel Mailable instance.
     *
     * @throws RuntimeException When delivery fails.
     */
    public function send(string $to, ?string $name, Mailable $mailable): void;
}
