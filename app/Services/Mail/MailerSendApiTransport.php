<?php

namespace App\Services\Mail;

use App\Contracts\MailTransportInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Sends email through the MailerSend HTTPS API.
 *
 * No composer package required — uses Laravel's Http facade.
 * Preferred on A2Hosting where outbound SMTP is blocked.
 *
 * .env:
 *   MAIL_TRANSPORT=mailersend
 *   MAILERSEND_API_TOKEN=mlsn.xxxx
 *   MAIL_FROM_ADDRESS=noreply@yourdomain.com
 *   MAIL_FROM_NAME="FLISoL"
 *
 * @see https://developers.mailersend.com/api/v1/email.html#send-an-email
 */
class MailerSendApiTransport implements MailTransportInterface
{
    private const ENDPOINT = 'https://api.mailersend.com/v1/email';

    public function __construct(
        private readonly string $apiToken,
        private readonly string $fromAddress,
        private readonly string $fromName,
    )
    {
    }

    public function send(string $to, ?string $name, Mailable $mailable): void
    {
        $html = $mailable->render();
        $subject = $this->resolveSubject($mailable);

        $payload = [
            'from' => [
                'email' => $this->fromAddress,
                'name' => $this->fromName,
            ],
            'to' => [[
                'email' => $to,
                'name' => $name ?? $to,
            ]],
            'subject' => $subject,
            'html' => $html,
            'text' => strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html)),
        ];

        $response = Http::withToken($this->apiToken)
            ->acceptJson()
            ->timeout(30)
            ->post(self::ENDPOINT, $payload);

        if ($response->failed()) {
            $status = $response->status();
            $body = $response->json();
            $message = $body['message'] ?? $response->body();

            // Log details but never the token
            Log::error('MailerSendApiTransport: API error', [
                'to' => $to,
                'subject' => $subject,
                'status' => $status,
                'message' => $message,
                'errors' => $body['errors'] ?? [],
            ]);

            throw new RuntimeException(
                "MailerSend API returned HTTP {$status} for <{$to}>: {$message}"
            );
        }

        Log::info('MailerSendApiTransport: accepted', [
            'to' => $to,
            'subject' => $subject,
            'status' => $response->status(), // 202 = queued by MailerSend
        ]);
    }

    private function resolveSubject(Mailable $mailable): string
    {
        try {
            $subject = $mailable->envelope()->subject;
            if (!empty($subject)) {
                return $subject;
            }
        } catch (Throwable) {
            // envelope() not overridden — fall through
        }

        return 'Notificação — ' . config('app.name', 'FLISoL');
    }
}
