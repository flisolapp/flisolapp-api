<?php

namespace Tests\Feature\Admin;

use App\Mail\CertificateAvailableMail;
use App\Models\Edition;
use App\Models\People;
use App\Models\PeopleCertificate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Feature tests for POST /api/admin/certificates/send-mail/{code}
 *
 * Each test starts with a clean database (RefreshDatabase) and a
 * freshly authenticated admin user so that the Sanctum middleware
 * passes without extra ceremony.
 *
 * The mail facade is always faked so no real SMTP connection is made.
 */
class CertificatesSendMailTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function actingAsAdmin(): static
    {
        return $this->actingAs(User::factory()->create(), 'sanctum');
    }

    private function createEdition(array $overrides = []): Edition
    {
        return Edition::create(array_merge([
            'year' => '2025',
            'active' => 1,
            'name' => 'FLISoL 2025',
        ], $overrides));
    }

    private function createPerson(array $overrides = []): People
    {
        static $counter = 0;
        $counter++;

        return People::create(array_merge([
            'name' => 'Test Person ' . $counter,
            'email' => 'person' . $counter . '@example.com',
            'federal_code' => null,
        ], $overrides));
    }

    /** Defaults to an eligible (unsent, released, not removed) certificate. */
    private function createCertificate(People $person, Edition $edition, array $overrides = []): PeopleCertificate
    {
        return PeopleCertificate::create(array_merge([
            'people_id' => $person->id,
            'edition_id' => $edition->id,
            'name' => $person->name,
            'code' => 'TEST-' . strtoupper(uniqid()),
            'sent_at' => null,
            'removed_at' => null,
        ], $overrides));
    }

    private function url(string $code): string
    {
        return '/api/admin/certificates/send-mail/' . $code;
    }

    // ── 404 – certificate not found ───────────────────────────────────────────

    public function test_returns_404_for_unknown_code(): void
    {
        Mail::fake();

        $this->actingAsAdmin()
            ->postJson($this->url('DOES-NOT-EXIST'))
            ->assertStatus(404)
            ->assertJsonFragment(['error' => 'Certificate not found.']);

        Mail::assertNothingSent();
    }

    // ── 422 – certificate ineligible ──────────────────────────────────────────

    public function test_returns_422_for_removed_certificate(): void
    {
        Mail::fake();
        $cert = $this->createCertificate(
            $this->createPerson(),
            $this->createEdition(),
            ['removed_at' => now()],
        );

        $this->actingAsAdmin()
            ->postJson($this->url($cert->code))
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'Certificate has been removed and cannot be sent.']);

        Mail::assertNothingSent();
    }

    public function test_returns_422_when_person_has_no_email(): void
    {
        Mail::fake();
        $cert = $this->createCertificate(
            $this->createPerson(['email' => '']),
            $this->createEdition(),
        );

        $this->actingAsAdmin()
            ->postJson($this->url($cert->code))
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'Person has no email address on record.']);

        Mail::assertNothingSent();
    }

    // ── 409 – already sent ────────────────────────────────────────────────────

    public function test_returns_409_when_certificate_already_sent(): void
    {
        Mail::fake();
        $sentAt = Carbon::now()->subHour();
        $cert = $this->createCertificate(
            $this->createPerson(),
            $this->createEdition(),
            ['sent_at' => $sentAt],
        );

        $this->actingAsAdmin()
            ->postJson($this->url($cert->code))
            ->assertStatus(409)
            ->assertJsonFragment(['error' => 'Certificate email was already sent.'])
            ->assertJsonPath('code', $cert->code);

        Mail::assertNothingSent();
    }

    // ── 201 – happy path ──────────────────────────────────────────────────────

    public function test_sends_email_and_stamps_target_certificate(): void
    {
        Mail::fake();
        $person = $this->createPerson();
        $edition = $this->createEdition();
        $cert = $this->createCertificate($person, $edition);

        $this->actingAsAdmin()
            ->postJson($this->url($cert->code))
            ->assertStatus(201)
            ->assertJsonFragment([
                'message' => 'Certificate availability email sent successfully.',
                'certificates_sent' => 1,
            ])
            ->assertJsonPath('person.id', $person->id)
            ->assertJsonPath('person.email', $person->email);

        Mail::assertSent(CertificateAvailableMail::class, fn($m) => $m->hasTo($person->email));
        $this->assertNotNull($cert->fresh()->sent_at);
    }

    public function test_stamps_all_pending_certificates_of_the_same_person(): void
    {
        Mail::fake();
        $person = $this->createPerson();
        $edition = $this->createEdition();
        $cert1 = $this->createCertificate($person, $edition);
        $cert2 = $this->createCertificate($person, $edition);

        // Target cert1 — cert2 must be stamped as well (same person, same edition)
        $this->actingAsAdmin()
            ->postJson($this->url($cert1->code))
            ->assertStatus(201)
            ->assertJsonFragment(['certificates_sent' => 2]);

        $this->assertNotNull($cert1->fresh()->sent_at);
        $this->assertNotNull($cert2->fresh()->sent_at);
        Mail::assertSentCount(1);
    }

    public function test_does_not_overwrite_already_sent_certificate_of_same_person(): void
    {
        Mail::fake();
        $person = $this->createPerson();
        $edition = $this->createEdition();
        $originalTs = Carbon::now()->subDay();

        $pending = $this->createCertificate($person, $edition);
        $alreadySent = $this->createCertificate($person, $edition, ['sent_at' => $originalTs]);

        $this->actingAsAdmin()
            ->postJson($this->url($pending->code))
            ->assertStatus(201)
            ->assertJsonFragment(['certificates_sent' => 1]);

        $this->assertEquals(
            $originalTs->toDateTimeString(),
            $alreadySent->fresh()->sent_at->toDateTimeString(),
            'Existing sent_at must not be overwritten.',
        );
    }

    public function test_does_not_stamp_removed_certificates_of_same_person(): void
    {
        Mail::fake();
        $person = $this->createPerson();
        $edition = $this->createEdition();
        $pending = $this->createCertificate($person, $edition);
        $removed = $this->createCertificate($person, $edition, ['removed_at' => now()]);

        $this->actingAsAdmin()
            ->postJson($this->url($pending->code))
            ->assertStatus(201)
            ->assertJsonFragment(['certificates_sent' => 1]);

        $this->assertNull($removed->fresh()->sent_at);
    }

    public function test_does_not_stamp_certificates_from_other_editions(): void
    {
        Mail::fake();
        $person = $this->createPerson();
        $edition1 = $this->createEdition(['year' => '2025', 'active' => 1]);
        $edition2 = $this->createEdition(['year' => '2024', 'active' => 0]);

        $target = $this->createCertificate($person, $edition1);
        $other = $this->createCertificate($person, $edition2);

        $this->actingAsAdmin()
            ->postJson($this->url($target->code))
            ->assertStatus(201);

        $this->assertNotNull($target->fresh()->sent_at);
        $this->assertNull($other->fresh()->sent_at, 'Certificate from another edition must not be stamped.');
    }

    public function test_two_different_people_require_two_separate_calls(): void
    {
        Mail::fake();
        $edition = $this->createEdition();
        $person1 = $this->createPerson();
        $person2 = $this->createPerson();
        $cert1 = $this->createCertificate($person1, $edition);
        $cert2 = $this->createCertificate($person2, $edition);

        $this->actingAsAdmin()->postJson($this->url($cert1->code))->assertStatus(201);

        // After one call only person1 is done
        $this->assertNotNull($cert1->fresh()->sent_at);
        $this->assertNull($cert2->fresh()->sent_at);
        Mail::assertSentCount(1);

        $this->actingAsAdmin()->postJson($this->url($cert2->code))->assertStatus(201);

        $this->assertNotNull($cert2->fresh()->sent_at);
        Mail::assertSentCount(2);
    }

    // ── Authentication guard ──────────────────────────────────────────────────

    public function test_unauthenticated_request_is_rejected(): void
    {
        Mail::fake();
        $cert = $this->createCertificate($this->createPerson(), $this->createEdition());

        $this->postJson($this->url($cert->code))
            ->assertStatus(401);

        Mail::assertNothingSent();
    }
}
