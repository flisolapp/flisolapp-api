<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\CertificateHelper;
use App\Helpers\StringHelper;
use App\Http\Controllers\Controller;
use App\Models\Collaborator;
use App\Models\Edition;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\PeopleCertificate;
use App\Models\Talk;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CertificatesReleaseController extends Controller
{
    /**
     * Release certificates for the current active edition.
     *
     * Creates missing certificate records for all eligible people:
     *  - Organizers  (active, not removed)
     *  - Collaborators (active, not removed)
     *  - Speakers (talk not removed AND talk was presented)
     *  - Participants (not removed AND attended: presented_at IS NOT NULL)
     *
     * Phase 1 — insert missing records — runs inside a transaction.
     * Phase 2 — assign unique verification codes and normalise names — runs
     *           outside the transaction (idempotent, safe to retry).
     *
     * GET /api/admin/certificates/release
     *
     * Possible responses:
     * - 200: Certificates released successfully
     * - 404: No active edition found
     * - 500: Failed to populate certificate records
     */
    public function execute(): JsonResponse
    {
        set_time_limit(0);

        $edition = Edition::where('active', 1)->orderByDesc('id')->first();

        if (!$edition) {
            return response()->json(['error' => 'No active edition found.'], 404);
        }

        $now = Carbon::now();

        // ── Phase 1: insert missing certificate records ───────────────────────
        DB::beginTransaction();

        try {
            $this->releaseOrganizerCertificates($edition->id, $now);
            $this->releaseCollaboratorCertificates($edition->id, $now);
            $this->releaseSpeakerCertificates($edition->id, $now);
            $this->releaseParticipantCertificates($edition->id, $now);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('CertificatesReleaseController: error populating certificates: ' . $e->getMessage(), [
                'edition_id' => $edition->id,
                'exception' => $e,
            ]);

            return response()->json(['error' => 'Failed to populate certificates.'], 500);
        }

        // ── Phase 2: assign verification codes and normalise names ────────────
        PeopleCertificate::where('edition_id', $edition->id)
            ->whereNull('removed_at')
            ->whereNull('code')
            ->get()
            ->each(function (PeopleCertificate $certificate) use ($now): void {
                $certificate->name = StringHelper::prepareName($certificate->name ?? '');
                $certificate->code = $this->generateUniqueCode();
                $certificate->updated_at = $now;
                $certificate->save();
            });

        return response()->json(['message' => 'Certificates released successfully.']);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function releaseOrganizerCertificates(int $editionId, Carbon $now): void
    {
        Organizer::where('edition_id', $editionId)
            ->whereNull('removed_at')
            ->with('person')
            ->get()
            ->each(function (Organizer $organizer) use ($editionId, $now): void {
                if (!$organizer->person) {
                    Log::warning('CertificatesReleaseController: organizer has no person', [
                        'organizer_id' => $organizer->id,
                    ]);
                    return;
                }

                $alreadyExists = PeopleCertificate::where([
                    ['people_id', $organizer->people_id],
                    ['edition_id', $editionId],
                    ['organizer_id', $organizer->id],
                    ['collaborator_id', null],
                    ['talk_id', null],
                    ['participant_id', null],
                ])->whereNull('removed_at')->exists();

                if ($alreadyExists) {
                    return;
                }

                PeopleCertificate::create([
                    'people_id' => $organizer->people_id,
                    'edition_id' => $editionId,
                    'organizer_id' => $organizer->id,
                    'name' => $organizer->person->name,
                    'federal_code' => $organizer->person->federal_code,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    private function releaseCollaboratorCertificates(int $editionId, Carbon $now): void
    {
        Collaborator::where('edition_id', $editionId)
            ->whereNotNull('presented_at')
            ->whereNull('removed_at')
            ->with('person')
            ->get()
            ->each(function (Collaborator $collaborator) use ($editionId, $now): void {
                if (!$collaborator->person) {
                    Log::warning('CertificatesReleaseController: collaborator has no person', [
                        'collaborator_id' => $collaborator->id,
                    ]);
                    return;
                }

                $alreadyExists = PeopleCertificate::where([
                    ['people_id', $collaborator->people_id],
                    ['edition_id', $editionId],
                    ['organizer_id', null],
                    ['collaborator_id', $collaborator->id],
                    ['talk_id', null],
                    ['participant_id', null],
                ])->whereNull('removed_at')->exists();

                if ($alreadyExists) {
                    return;
                }

                PeopleCertificate::create([
                    'people_id' => $collaborator->people_id,
                    'edition_id' => $editionId,
                    'collaborator_id' => $collaborator->id,
                    'name' => $collaborator->person->name,
                    'federal_code' => $collaborator->person->federal_code,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    private function releaseSpeakerCertificates(int $editionId, Carbon $now): void
    {
        Talk::where('edition_id', $editionId)
            ->whereNull('removed_at')
            ->whereNotNull('presented_at')
            ->with(['speakerTalks' => fn($q) => $q->whereNull('removed_at'), 'speakerTalks.speaker'])
            ->get()
            ->each(function (Talk $talk) use ($editionId, $now): void {
                foreach ($talk->speakerTalks as $speakerTalk) {
                    if (!$speakerTalk->speaker) {
                        Log::warning('CertificatesReleaseController: speakerTalk has no speaker', [
                            'speaker_talk_id' => $speakerTalk->id,
                        ]);
                        continue;
                    }

                    $alreadyExists = PeopleCertificate::where([
                        ['people_id', $speakerTalk->speaker_id],
                        ['edition_id', $editionId],
                        ['organizer_id', null],
                        ['collaborator_id', null],
                        ['talk_id', $talk->id],
                        ['participant_id', null],
                    ])->whereNull('removed_at')->exists();

                    if ($alreadyExists) {
                        continue;
                    }

                    PeopleCertificate::create([
                        'people_id' => $speakerTalk->speaker_id,
                        'edition_id' => $editionId,
                        'talk_id' => $talk->id,
                        'name' => $speakerTalk->speaker->name,
                        'federal_code' => $speakerTalk->speaker->federal_code,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    private function releaseParticipantCertificates(int $editionId, Carbon $now): void
    {
        Participant::where('edition_id', $editionId)
            ->whereNull('removed_at')
            ->whereNotNull('presented_at')
            ->with('person')
            ->get()
            ->each(function (Participant $participant) use ($editionId, $now): void {
                if (!$participant->person) {
                    Log::warning('CertificatesReleaseController: participant has no person', [
                        'participant_id' => $participant->id,
                    ]);
                    return;
                }

                $alreadyExists = PeopleCertificate::where([
                    ['people_id', $participant->people_id],
                    ['edition_id', $editionId],
                    ['organizer_id', null],
                    ['collaborator_id', null],
                    ['talk_id', null],
                    ['participant_id', $participant->id],
                ])->whereNull('removed_at')->exists();

                if ($alreadyExists) {
                    return;
                }

                PeopleCertificate::create([
                    'people_id' => $participant->people_id,
                    'edition_id' => $editionId,
                    'participant_id' => $participant->id,
                    'name' => $participant->person->name,
                    'federal_code' => $participant->person->federal_code,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    /**
     * Generate a unique public verification code.
     * Uses withTrashed() to avoid reusing a code from a removed certificate.
     */
    private function generateUniqueCode(): string
    {
        do {
            $code = CertificateHelper::generateCode();
        } while (PeopleCertificate::withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
