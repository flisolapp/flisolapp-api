<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\StringHelper;
use App\Http\Controllers\Controller;
use App\Mail\CertificateAvailableMail;
use App\Models\People;
use App\Models\PeopleCertificate;
use App\Services\MailerService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CertificatesSendController extends Controller
{
    /**
     * Send the certificate-availability email for a specific certificate code.
     *
     * The admin explicitly targets ONE certificate by its public verification
     * code.  From that certificate the person is resolved, and every other
     * pending certificate belonging to the same person in the same edition is
     * also stamped — so the person always receives a single email regardless
     * of how many certificates they hold.
     *
     * A certificate is eligible when:
     *  - removed_at IS NULL
     *  - sent_at    IS NULL
     *  - code       IS NOT NULL  (already released)
     *
     * POST /api/admin/certificates/send-mail/{code}
     *
     * Possible responses:
     *  201  – Email sent; body contains person/certificate summary.
     *  404  – Certificate not found for the given code.
     *  409  – Certificate was already sent (sent_at IS NOT NULL).
     *  422  – Certificate is removed, has no code, or person has no email.
     *  500  – Mailer error; no certificates were marked as sent.
     */
    public function execute(string $code): JsonResponse
    {
        // ── Resolve the target certificate ────────────────────────────────────
        /** @var PeopleCertificate|null $certificate */
        $certificate = PeopleCertificate::where('code', $code)->first();

        if (!$certificate) {
            return response()->json([
                'error' => 'Certificate not found.',
                'code' => $code,
            ], 404);
        }

        if ($certificate->removed_at !== null) {
            return response()->json([
                'error' => 'Certificate has been removed and cannot be sent.',
                'code' => $code,
            ], 422);
        }

        if ($certificate->sent_at !== null) {
            return response()->json([
                'error' => 'Certificate email was already sent.',
                'code' => $code,
                'sent_at' => $certificate->sent_at,
            ], 409);
        }

        // ── Load the person and validate email ────────────────────────────────
        /** @var People|null $person */
        $person = People::whereNull('removed_at')->find($certificate->people_id);

        if (!$person) {
            Log::warning('CertificatesSendController: person not found or removed', [
                'people_id' => $certificate->people_id,
                'edition_id' => $certificate->edition_id,
                'code' => $code,
            ]);

            return response()->json([
                'error' => 'Person not found or has been removed.',
                'people_id' => $certificate->people_id,
            ], 422);
        }

        // ── Sanitise name and email before using them ─────────────────────────
        //
        // People sometimes register with ALL-CAPS names, extra whitespace, or
        // mixed-case email addresses.  We normalise here so the Mailable and
        // the mailer both receive clean values regardless of what is stored.
        //
        // Name: trim and collapse internal spaces; must be non-empty.
        // Email: trim and lowercase; must pass filter_var validation.
        $name = trim(preg_replace('/\s+/', ' ', $person->name ?? ''));
        $email = strtolower(trim($person->email ?? ''));

        if ($name === '') {
            Log::warning('CertificatesSendController: person has no valid name', [
                'people_id' => $person->id,
                'edition_id' => $certificate->edition_id,
                'code' => $code,
            ]);

            return response()->json([
                'error' => 'Person has no valid name on record.',
                'people_id' => $person->id,
            ], 422);
        }

        $firstName = StringHelper::firstName($name);

        if ($firstName === '') {
            Log::warning('CertificatesSendController: person has no valid first name', [
                'people_id' => $person->id,
                'edition_id' => $certificate->edition_id,
                'code' => $code,
            ]);

            return response()->json([
                'error' => 'Person has no valid first name on record.',
                'people_id' => $person->id,
            ], 422);
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('CertificatesSendController: person has no valid email', [
                'people_id' => $person->id,
                'edition_id' => $certificate->edition_id,
                'email_raw' => $person->email,
                'email_cleaned' => $email,
                'code' => $code,
            ]);

            return response()->json([
                'error' => 'Person has no valid email address on record.',
                'people_id' => $person->id,
                'name' => $name,
            ], 422);
        }

        // ── Send email ────────────────────────────────────────────────────────
        //
        // The mail is sent BEFORE opening the transaction.  If the mailer
        // throws, no DB state changes.  If the transaction rolls back after a
        // successful send the worst outcome is a duplicate email on retry —
        // acceptable and far better than a silent miss.
        try {
            // Mail::to($email)->send(new CertificateAvailableMail($firstName, $email));
            app(MailerService::class)->send(
                to: $email,
                mailable: new CertificateAvailableMail($firstName, $email),
                name: $firstName,
            );
        } catch (Exception $e) {
            Log::error('CertificatesSendController: mailer error', [
                'people_id' => $person->id,
                'email' => $email,
                'edition_id' => $certificate->edition_id,
                'code' => $code,
                'exception' => $e,
            ]);

            return response()->json([
                'error' => 'Failed to send email. No certificates were marked as sent.',
                'details' => $e->getMessage(),
            ], 500);
        }

        // ── Stamp sent_at for every pending certificate of this person in the
        //    same edition — same rule as before, scoped by edition so historical
        //    editions are never touched.
        $now = Carbon::now();

        DB::beginTransaction();

        try {
            PeopleCertificate::where('people_id', $person->id)
                ->where('edition_id', $certificate->edition_id)
                ->whereNull('removed_at')
                ->whereNull('sent_at')
                ->whereNotNull('code')
                ->update([
                    'sent_at' => $now,
                    'updated_at' => $now,
                ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            // The email was already sent.  Log prominently so an admin can
            // manually reconcile if needed.
            Log::error('CertificatesSendController: email sent but failed to stamp sent_at', [
                'people_id' => $person->id,
                'email' => $email,
                'edition_id' => $certificate->edition_id,
                'code' => $code,
                'exception' => $e,
            ]);

            return response()->json([
                'error' => 'Email was sent but certificates could not be marked as sent. Please reconcile manually.',
                'details' => $e->getMessage(),
            ], 500);
        }

        // ── Reload stamped certificates for the response payload ──────────────
        $sentCertificates = PeopleCertificate::where('people_id', $person->id)
            ->where('edition_id', $certificate->edition_id)
            ->whereNull('removed_at')
            ->whereNotNull('sent_at')
            ->whereNotNull('code')
            ->get(['id', 'code', 'sent_at']);

        Log::info('CertificatesSendController: email sent', [
            'people_id' => $person->id,
            'email' => $email,
            'edition_id' => $certificate->edition_id,
            'code' => $code,
            'certificates_sent' => $sentCertificates->count(),
        ]);

        return response()->json([
            'message' => 'Certificate availability email sent successfully.',
            'edition_id' => $certificate->edition_id,
            'person' => [
                'id' => $person->id,
                'name' => $name,
                'email' => $email,
            ],
            'certificates_sent' => $sentCertificates->count(),
            'certificates' => $sentCertificates->map(fn($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'sent_at' => $c->sent_at,
            ])->values(),
        ], 201);
    }
}
