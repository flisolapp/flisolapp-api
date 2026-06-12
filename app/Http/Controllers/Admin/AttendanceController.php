<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collaborator;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\SpeakerTalk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AttendanceController extends Controller
{
    /**
     * GET /api/attendance
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'edition_id' => ['nullable', 'integer', 'exists:editions,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'string', 'in:id,name,email,phone,federal_code,kind,talk_title,talk_shift,checked_in,checked_in_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $editionId = $data['edition_id'] ?? 22;
        $page = (int)($data['page'] ?? 1);
        $perPage = (int)($data['per_page'] ?? 10);
        $search = trim($data['search'] ?? '');
        $sortBy = $data['sort_by'] ?? 'name';
        $sortDirection = strtolower($data['sort_direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $rows = collect()
            ->merge($this->getParticipantRows($editionId, $search))
            ->merge($this->getCollaboratorRows($editionId, $search))
            ->merge($this->getOrganizerRows($editionId, $search))
            ->merge($this->getSpeakerTalkRows($editionId, $search));

        $rows = $this->sortRows($rows, $sortBy, $sortDirection);

        $total = $rows->count();
        $items = $rows
            ->slice(($page - 1) * $perPage, $perPage)
            ->values();

        return response()->json([
            'data' => $items,
            'current_page' => $page,
            'last_page' => max(1, (int)ceil($total / $perPage)),
            'per_page' => $perPage,
            'total' => $total,
        ]);
    }

    /**
     * PATCH /api/attendance/{kind}/{id}/check-in
     */
    public function toggleCheckIn(Request $request, string $kind, int $id, $data = null): JsonResponse
    {
        $data = $request->validate([
            'checked_in' => ['required', 'boolean'],
            'data' => ['nullable', 'array'],
            'data.name' => ['nullable', 'string', 'max:255'],
            'data.federal_code' => ['nullable', 'string', 'max:255'],
            'data.email' => ['nullable', 'email', 'max:255'],
            'data.phone' => ['nullable', 'string', 'max:255'],
        ]);

        $kind = strtolower($kind);

        if ($kind === 'speaker_talk') {
            $speakerTalk = SpeakerTalk::query()
                ->with(['speaker', 'talk', 'talk.talkSubject'])
                ->whereNull('speaker_talks.removed_at')
                ->find($id);

            if (
                !$speakerTalk ||
                !$speakerTalk->speaker ||
                !$speakerTalk->talk ||
                $speakerTalk->speaker->removed_at !== null ||
                $speakerTalk->talk->removed_at !== null
            ) {
                return response()->json([
                    'message' => 'Attendance record not found.',
                ], 404);
            }

            $speakerTalk->talk->update([
                'presented_at' => $data['checked_in'] ? now() : null,
            ]);

            if ($data['checked_in'] === true && isset($data['data']) && is_array($data['data'])) {
                $personData = collect($data['data'])
                    ->only([
                        'name',
                        'federal_code',
                        'email',
                        'phone',
                    ])
                    ->filter(fn($value) => $value !== null)
                    ->toArray();

                if (!empty($personData)) {
                    $speakerTalk->speaker->update($personData);
                }
            }

            $speakerTalk->refresh()->load(['speaker', 'talk', 'talk.talkSubject']);
            $speakerTalk->talk->refresh();

            return response()->json($this->formatSpeakerTalk($speakerTalk));
        }

        [$modelClass, $field] = match ($kind) {
            'participant' => [Participant::class, 'presented_at'],
            'collaborator' => [Collaborator::class, 'presented_at'],
            'organizer' => [Organizer::class, 'presented_at'],
            default => [null, null],
        };

        if (!$modelClass || !$field) {
            return response()->json([
                'message' => 'Invalid attendance kind.',
            ], 422);
        }

        $record = $modelClass::query()
            ->with(['person', 'edition'])
            ->whereNull('removed_at')
            ->find($id);

        if (
            !$record ||
            !$record->person ||
            $record->person->removed_at !== null
        ) {
            return response()->json([
                'message' => 'Attendance record not found.',
            ], 404);
        }

        $record->update([
            $field => $data['checked_in'] ? now() : null,
        ]);

        if ($data['checked_in'] === true && isset($data['data']) && is_array($data['data'])) {
            $personData = collect($data['data'])
                ->only([
                    'name',
                    'federal_code',
                    'email',
                    'phone',
                ])
                ->filter(fn($value) => $value !== null)
                ->toArray();

            if (!empty($personData)) {
                $record->person->update($personData);
            }
        }

        $record->refresh()->load(['person', 'edition']);

        return response()->json(match ($kind) {
            'participant' => $this->formatParticipant($record),
            'collaborator' => $this->formatCollaborator($record),
            'organizer' => $this->formatOrganizer($record),
        });
    }

    /**
     * Future endpoint placeholder
     * PATCH /api/attendance/{kind}/{id}/check-out
     */
    public function checkOut(Request $request, string $kind, int $id): JsonResponse
    {
        return response()->json([
            'message' => 'Check-out is not available yet.',
        ], 501);
    }

    /**
     * Future endpoint placeholder
     * GET /api/attendance/{kind}/{id}/print-label
     */
    public function printLabel(string $kind, int $id): JsonResponse
    {
        return response()->json([
            'message' => 'Print label is not available yet.',
        ], 501);
    }

    private function getParticipantRows(int $editionId, string $search): Collection
    {
        return Participant::query()
            ->select('participants.*')
            ->with(['person', 'edition'])
            ->join('people', 'people.id', '=', 'participants.people_id')
            ->whereNull('participants.removed_at')
            ->whereNull('people.removed_at')
            ->where('participants.edition_id', $editionId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('people.name', 'like', '%' . $search . '%')
                        ->orWhere('people.email', 'like', '%' . $search . '%')
                        ->orWhere('people.phone', 'like', '%' . $search . '%')
                        ->orWhere('people.federal_code', 'like', '%' . $search . '%')
                        ->orWhere('participants.id', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('participants.id', 'desc')
            ->get()
            ->map(fn(Participant $participant) => $this->formatParticipant($participant));
    }

    private function getCollaboratorRows(int $editionId, string $search): Collection
    {
        return Collaborator::query()
            ->select('collaborators.*')
            ->with(['person', 'edition'])
            ->join('people', 'people.id', '=', 'collaborators.people_id')
            ->whereNull('collaborators.removed_at')
            ->whereNull('people.removed_at')
            ->where('collaborators.edition_id', $editionId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('people.name', 'like', '%' . $search . '%')
                        ->orWhere('people.email', 'like', '%' . $search . '%')
                        ->orWhere('people.phone', 'like', '%' . $search . '%')
                        ->orWhere('people.federal_code', 'like', '%' . $search . '%')
                        ->orWhere('collaborators.id', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('collaborators.id', 'desc')
            ->get()
            ->map(fn(Collaborator $collaborator) => $this->formatCollaborator($collaborator));
    }

    private function getOrganizerRows(int $editionId, string $search): Collection
    {
        return Organizer::query()
            ->select('organizers.*')
            ->with(['person', 'edition'])
            ->join('people', 'people.id', '=', 'organizers.people_id')
            ->whereNull('organizers.removed_at')
            ->whereNull('people.removed_at')
            ->where('organizers.edition_id', $editionId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('people.name', 'like', '%' . $search . '%')
                        ->orWhere('people.email', 'like', '%' . $search . '%')
                        ->orWhere('people.phone', 'like', '%' . $search . '%')
                        ->orWhere('people.federal_code', 'like', '%' . $search . '%')
                        ->orWhere('organizers.id', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('organizers.id', 'desc')
            ->get()
            ->map(fn(Organizer $organizer) => $this->formatOrganizer($organizer));
    }

    private function getSpeakerTalkRows(int $editionId, string $search): Collection
    {
        return SpeakerTalk::query()
            ->select('speaker_talks.*')
            ->with(['speaker', 'talk', 'talk.talkSubject'])
            ->join('people', 'people.id', '=', 'speaker_talks.speaker_id')
            ->join('talks', 'talks.id', '=', 'speaker_talks.talk_id')
            ->leftJoin('talk_subjects', 'talk_subjects.id', '=', 'talks.talk_subject_id')
            ->whereNull('speaker_talks.removed_at')
            ->whereNull('people.removed_at')
            ->whereNull('talks.removed_at')
            ->where('talks.edition_id', $editionId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('people.name', 'like', '%' . $search . '%')
                        ->orWhere('people.email', 'like', '%' . $search . '%')
                        ->orWhere('people.phone', 'like', '%' . $search . '%')
                        ->orWhere('people.federal_code', 'like', '%' . $search . '%')
                        ->orWhere('talks.title', 'like', '%' . $search . '%')
                        ->orWhere('talks.description', 'like', '%' . $search . '%')
                        ->orWhere('talk_subjects.name', 'like', '%' . $search . '%')
                        ->orWhere('speaker_talks.id', 'like', '%' . $search . '%')
                        ->orWhere('talks.id', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('speaker_talks.id', 'desc')
            ->get()
            ->map(fn(SpeakerTalk $speakerTalk) => $this->formatSpeakerTalk($speakerTalk));
    }

    private function sortRows(Collection $rows, string $sortBy, string $sortDirection): Collection
    {
        $sorted = $rows->sortBy(function (array $row) use ($sortBy) {
            return match ($sortBy) {
                'id' => $row['id'] ?? 0,
                'email' => mb_strtolower($row['email'] ?? ''),
                'phone' => mb_strtolower($row['phone'] ?? ''),
                'federal_code' => mb_strtolower($row['federal_code'] ?? ''),
                'kind' => mb_strtolower($row['kind_label'] ?? ''),
                'talk_title' => mb_strtolower($row['talk_title'] ?? ''),
                'talk_shift' => mb_strtolower($row['talk_shift'] ?? ''),
                'checked_in' => ($row['checked_in'] ?? false) ? 1 : 0,
                'checked_in_at' => $row['checked_in_at'] ?? '',
                default => mb_strtolower($row['name'] ?? ''),
            };
        });

        return $sortDirection === 'desc'
            ? $sorted->reverse()->values()
            : $sorted->values();
    }

    private function formatParticipant(Participant $participant): array
    {
        return [
            'id' => $participant->id,
            'kind' => 'participant',
            'kind_label' => 'Participant',
            'edition_id' => $participant->edition_id,
            'people_id' => $participant->people_id,
            'talk_id' => null,
            'name' => $participant->person?->name,
            'email' => $participant->person?->email,
            'phone' => $participant->person?->phone,
            'federal_code' => $participant->person?->federal_code,
            'talk_title' => null,
            'talk_kind' => null,
            'talk_shift' => null,
            'talk_subject' => null,
            'checked_in' => $participant->presented_at !== null,
            'checked_in_at' => optional($participant->presented_at)?->toIso8601String(),
        ];
    }

    private function formatCollaborator(Collaborator $collaborator): array
    {
        return [
            'id' => $collaborator->id,
            'kind' => 'collaborator',
            'kind_label' => 'Collaborator',
            'edition_id' => $collaborator->edition_id,
            'people_id' => $collaborator->people_id,
            'talk_id' => null,
            'name' => $collaborator->person?->name,
            'email' => $collaborator->person?->email,
            'phone' => $collaborator->person?->phone,
            'federal_code' => $collaborator->person?->federal_code,
            'talk_title' => null,
            'talk_kind' => null,
            'talk_shift' => null,
            'talk_subject' => null,
            'checked_in' => $collaborator->presented_at !== null,
            'checked_in_at' => optional($collaborator->presented_at)?->toIso8601String(),
        ];
    }

    private function formatOrganizer(Organizer $organizer): array
    {
        return [
            'id' => $organizer->id,
            'kind' => 'organizer',
            'kind_label' => 'Organizer',
            'edition_id' => $organizer->edition_id,
            'people_id' => $organizer->people_id,
            'talk_id' => null,
            'name' => $organizer->person?->name,
            'email' => $organizer->person?->email,
            'phone' => $organizer->person?->phone,
            'federal_code' => $organizer->person?->federal_code,
            'talk_title' => null,
            'talk_kind' => null,
            'talk_shift' => null,
            'talk_subject' => null,
            'checked_in' => $organizer->presented_at !== null,
            'checked_in_at' => optional($organizer->presented_at)?->toIso8601String(),
        ];
    }

    private function formatSpeakerTalk(SpeakerTalk $speakerTalk): array
    {
        return [
            'id' => $speakerTalk->id,
            'kind' => 'speaker_talk',
            'kind_label' => 'Speaker/Talk',
            'edition_id' => $speakerTalk->talk?->edition_id,
            'people_id' => $speakerTalk->speaker_id,
            'talk_id' => $speakerTalk->talk_id,
            'name' => $speakerTalk->speaker?->name,
            'email' => $speakerTalk->speaker?->email,
            'phone' => $speakerTalk->speaker?->phone,
            'federal_code' => $speakerTalk->speaker?->federal_code,
            'talk_title' => $speakerTalk->talk?->title,
            'talk_kind' => $speakerTalk->talk?->kind,
            'talk_shift' => $speakerTalk->talk?->shift,
            'talk_subject' => $speakerTalk->talk?->talkSubject?->name,
            'checked_in' => $speakerTalk->talk?->presented_at !== null,
            'checked_in_at' => optional($speakerTalk->talk?->presented_at)?->toIso8601String(),
        ];
    }
}
