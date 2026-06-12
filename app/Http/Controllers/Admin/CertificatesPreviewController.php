<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificatesPreviewController extends Controller
{
    private const ROLE_ORGANIZER = 'Organizador';
    private const ROLE_COLLABORATOR = 'Colaborador';
    private const ROLE_SPEAKER = 'Palestrante';
    private const ROLE_PARTICIPANT = 'Participante';

    /**
     * List all valid certificate candidates for the active edition.
     *
     * Only records that would actually receive a certificate are returned:
     *  - Organizers: active (not removed)
     *  - Collaborators: active (not removed)
     *  - Speakers: talk not removed AND talk was presented
     *  - Participants: not removed AND attended (presented_at IS NOT NULL)
     *
     * GET /api/admin/certificates/preview
     */
    public function index(): JsonResponse
    {
        $edition = Edition::where('active', 1)->orderByDesc('id')->first();

        if (!$edition) {
            return response()->json(['error' => 'No active edition found.'], 404);
        }

        $candidates = $this->buildCandidates($edition->id, $edition->year);

        return response()->json([
            'edition_id' => $edition->id,
            'edition_year' => $edition->year,
            'total' => count($candidates),
            'data' => $candidates,
        ]);
    }

    /**
     * Download the valid candidate list as a UTF-8 CSV file.
     *
     * GET /api/admin/certificates/preview.csv
     */
    public function csv(): StreamedResponse
    {
        $edition = Edition::where('active', 1)->orderByDesc('id')->first();

        if (!$edition) {
            abort(404, 'No active edition found.');
        }

        $candidates = $this->buildCandidates($edition->id, $edition->year);
        $filename = 'certificates-preview-' . $edition->year . '.csv';

        return response()->streamDownload(function () use ($candidates): void {
            echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel

            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'id',
                'person_id',
                'name',
                'email',
                'federal_code',
                'role',
                'certificate_type',
                'edition_id',
                'edition_year',
                'role_record_id',
                'talk_id',
                'talk_title',
                'presented_at',
                'already_released',
                'cert_id',
                'cert_code',
                'cert_sent_at',
            ]);

            foreach ($candidates as $row) {
                fputcsv($handle, [
                    $row['id'],
                    $row['person_id'],
                    $row['name'],
                    $row['email'],
                    $row['federal_code'],
                    $row['role'],
                    $row['certificate_type'],
                    $row['edition_id'],
                    $row['edition_year'],
                    $row['role_record_id'],
                    $row['talk_id'],
                    $row['talk_title'],
                    $row['presented_at'],
                    $row['already_released'] ? 'sim' : 'não',
                    $row['cert_id'],
                    $row['cert_code'],
                    $row['cert_sent_at'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    /**
     * Build the candidate list applying the same eligibility rules used by
     * the release controller. Each row represents one certificate that will
     * be (or already has been) issued.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildCandidates(int $editionId, string $editionYear): array
    {
        $candidates = [];
        $counter = 0;

        // Pre-load active certificates for this edition so we can show
        // whether each candidate has already been released.
        $existingCerts = DB::table('people_certificates')
            ->where('edition_id', $editionId)
            ->whereNull('removed_at')
            ->get()
            ->keyBy(fn($row) => $this->certKey(
                $row->people_id,
                $row->organizer_id,
                $row->collaborator_id,
                $row->talk_id,
                $row->participant_id,
            ));

        // ── Organizers ────────────────────────────────────────────────────────
        $organizers = DB::table('organizers as o')
            ->join('people as p', 'p.id', '=', 'o.people_id')
            ->where('o.edition_id', $editionId)
            ->whereNull('o.removed_at')
            ->whereNull('p.removed_at')
            ->select('o.id as organizer_id', 'o.people_id', 'o.presented_at',
                'p.name', 'p.email', 'p.federal_code')
            ->get();

        foreach ($organizers as $row) {
            $cert = $existingCerts->get(
                $this->certKey($row->people_id, $row->organizer_id, null, null, null)
            );

            $candidates[] = $this->row(
                ++$counter, $row->people_id, $row->name, $row->email, $row->federal_code,
                self::ROLE_ORGANIZER, 'Certificado de Organização',
                $editionId, $editionYear,
                $row->organizer_id, null, null, $row->presented_at, $cert,
            );
        }

        // ── Collaborators ─────────────────────────────────────────────────────
        $collaborators = DB::table('collaborators as c')
            ->join('people as p', 'p.id', '=', 'c.people_id')
            ->where('c.edition_id', $editionId)
            ->whereNotNull('c.presented_at')
            ->whereNull('c.removed_at')
            ->whereNull('p.removed_at')
            ->select('c.id as collaborator_id', 'c.people_id', 'c.presented_at',
                'p.name', 'p.email', 'p.federal_code')
            ->get();

        foreach ($collaborators as $row) {
            $cert = $existingCerts->get(
                $this->certKey($row->people_id, null, $row->collaborator_id, null, null)
            );

            $candidates[] = $this->row(
                ++$counter, $row->people_id, $row->name, $row->email, $row->federal_code,
                self::ROLE_COLLABORATOR, 'Certificado de Colaboração',
                $editionId, $editionYear,
                $row->collaborator_id, null, null, $row->presented_at, $cert,
            );
        }

        // ── Speakers (only talks that were presented) ─────────────────────────
        $speakerTalks = DB::table('talks as t')
            ->join('speaker_talks as st', fn($j) => $j
                ->on('st.talk_id', '=', 't.id')
                ->whereNull('st.removed_at')
            )
            ->join('people as p', 'p.id', '=', 'st.speaker_id')
            ->where('t.edition_id', $editionId)
            ->whereNull('t.removed_at')
            ->whereNotNull('t.presented_at')
            ->whereNull('p.removed_at')
            ->select('st.speaker_id as people_id', 'st.id as speaker_talk_id',
                't.id as talk_id', 't.title as talk_title', 't.presented_at',
                'p.name', 'p.email', 'p.federal_code')
            ->get();

        foreach ($speakerTalks as $row) {
            $cert = $existingCerts->get(
                $this->certKey($row->people_id, null, null, $row->talk_id, null)
            );

            $candidates[] = $this->row(
                ++$counter, $row->people_id, $row->name, $row->email, $row->federal_code,
                self::ROLE_SPEAKER, 'Certificado de Palestrante',
                $editionId, $editionYear,
                $row->speaker_talk_id, $row->talk_id, $row->talk_title, $row->presented_at, $cert,
            );
        }

        // ── Participants (only those who attended) ────────────────────────────
        $participants = DB::table('participants as pa')
            ->join('people as p', 'p.id', '=', 'pa.people_id')
            ->where('pa.edition_id', $editionId)
            ->whereNull('pa.removed_at')
            ->whereNotNull('pa.presented_at')
            ->whereNull('p.removed_at')
            ->select('pa.id as participant_id', 'pa.people_id', 'pa.presented_at',
                'p.name', 'p.email', 'p.federal_code')
            ->get();

        foreach ($participants as $row) {
            $cert = $existingCerts->get(
                $this->certKey($row->people_id, null, null, null, $row->participant_id)
            );

            $candidates[] = $this->row(
                ++$counter, $row->people_id, $row->name, $row->email, $row->federal_code,
                self::ROLE_PARTICIPANT, 'Certificado de Participação',
                $editionId, $editionYear,
                $row->participant_id, null, null, $row->presented_at, $cert,
            );
        }

        return $candidates;
    }

    private function certKey(
        int  $peopleId,
        ?int $organizerId,
        ?int $collaboratorId,
        ?int $talkId,
        ?int $participantId,
    ): string
    {
        return implode('|', [
            $peopleId,
            $organizerId ?? 'null',
            $collaboratorId ?? 'null',
            $talkId ?? 'null',
            $participantId ?? 'null',
        ]);
    }

    private function row(
        int     $counter,
        int     $personId,
        string  $name,
        string  $email,
        ?string $federalCode,
        string  $role,
        string  $certificateType,
        int     $editionId,
        string  $editionYear,
        ?int    $roleRecordId,
        ?int    $talkId,
        ?string $talkTitle,
        ?string $presentedAt,
        mixed   $cert,
    ): array
    {
        return [
            'id' => $counter,
            'person_id' => $personId,
            'name' => $name,
            'email' => $email,
            'federal_code' => $federalCode,
            'role' => $role,
            'certificate_type' => $certificateType,
            'edition_id' => $editionId,
            'edition_year' => $editionYear,
            'role_record_id' => $roleRecordId,
            'talk_id' => $talkId,
            'talk_title' => $talkTitle,
            'presented_at' => $presentedAt,
            'already_released' => $cert !== null,
            'cert_id' => $cert?->id,
            'cert_code' => $cert?->code,
            'cert_sent_at' => $cert?->sent_at,
        ];
    }
}
