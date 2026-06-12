<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Participant;
use App\Models\People;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParticipantController extends Controller
{
    /**
     * GET /api/records/participants
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'edition_id' => ['nullable', 'integer', 'exists:editions,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'string', 'in:id,name,email,phone,federal_code,presented,created_at,updated_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $perPage = $data['per_page'] ?? 15;
        $search = trim($data['search'] ?? '');
        $sortBy = $data['sort_by'] ?? 'id';
        $sortDirection = strtolower($data['sort_direction'] ?? 'desc');

        $participants = Participant::query()
            ->select('participants.*')
            ->with(['person', 'edition'])
            ->join('people', 'people.id', '=', 'participants.people_id')
            ->whereNull('participants.removed_at')
            ->whereNull('people.removed_at')
            ->when(isset($data['edition_id']), function ($q) use ($data) {
                $q->where('participants.edition_id', $data['edition_id']);
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('people.name', 'like', '%' . $search . '%')
                        ->orWhere('people.email', 'like', '%' . $search . '%')
                        ->orWhere('people.phone', 'like', '%' . $search . '%')
                        ->orWhere('people.federal_code', 'like', '%' . $search . '%')
                        ->orWhere('participants.id', 'like', '%' . $search . '%');
                });
            });

        switch ($sortBy) {
            case 'name':
                $participants->orderBy('people.name', $sortDirection);
                break;

            case 'email':
                $participants->orderBy('people.email', $sortDirection);
                break;

            case 'phone':
                $participants->orderBy('people.phone', $sortDirection);
                break;

            case 'federal_code':
                $participants->orderBy('people.federal_code', $sortDirection);
                break;

            case 'presented':
                $participants->orderByRaw(
                    'CASE WHEN participants.presented_at IS NULL THEN 0 ELSE 1 END ' . $sortDirection
                );
                break;

            case 'created_at':
                $participants->orderBy('participants.created_at', $sortDirection);
                break;

            case 'updated_at':
                $participants->orderBy('participants.updated_at', $sortDirection);
                break;

            case 'id':
            default:
                $participants->orderBy('participants.id', $sortDirection);
                break;
        }

        $participants = $participants
            ->paginate($perPage)
            ->through(function (Participant $participant) {
                return $this->format($participant);
            });

        return response()->json($participants);
    }

    /**
     * GET /api/records/participants/{participant}
     */
    public function show(Participant $participant): JsonResponse
    {
        if ($participant->removed_at !== null) {
            return response()->json([
                'message' => 'Participant not found',
            ], 404);
        }

        $participant->load(['person', 'edition']);

        if (!$participant->person || $participant->person->removed_at !== null) {
            return response()->json([
                'message' => 'Participant not found',
            ], 404);
        }

        return response()->json($this->format($participant));
    }

    /**
     * POST /api/records/participants
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'federal_code' => ['nullable', 'string', 'max:255'],
            'edition_id' => ['required', 'exists:editions,id'],
        ]);

        $participant = DB::transaction(function () use ($data) {
            $person = People::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'federal_code' => $data['federal_code'] ?? null,
                'use_free' => false,
            ]);

            return Participant::create([
                'edition_id' => $data['edition_id'],
                'people_id' => $person->id,
            ]);
        });

        $participant->load(['person', 'edition']);

        return response()->json($this->format($participant), 201);
    }

    /**
     * PUT/PATCH /api/records/participants/{participant}
     */
    public function update(Request $request, Participant $participant): JsonResponse
    {
        if ($participant->removed_at !== null) {
            return response()->json([
                'message' => 'Participant not found',
            ], 404);
        }

        $participant->load(['person', 'edition']);

        if (!$participant->person || $participant->person->removed_at !== null) {
            return response()->json([
                'message' => 'Participant not found',
            ], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:255'],
            'federal_code' => ['nullable', 'string', 'max:255'],
            'edition_id' => ['sometimes', 'exists:editions,id'],
            'presented_at' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($participant, $data) {
            $personData = [];
            $participantData = [];

            if (array_key_exists('name', $data)) {
                $personData['name'] = $data['name'];
            }

            if (array_key_exists('email', $data)) {
                $personData['email'] = $data['email'];
            }

            if (array_key_exists('phone', $data)) {
                $personData['phone'] = $data['phone'];
            }

            if (array_key_exists('federal_code', $data)) {
                $personData['federal_code'] = $data['federal_code'];
            }

            if (!empty($personData)) {
                $participant->person->update($personData);
            }

            if (array_key_exists('edition_id', $data)) {
                $participantData['edition_id'] = $data['edition_id'];
            }

            if (array_key_exists('presented_at', $data)) {
                $participantData['presented_at'] = $data['presented_at'];
            }

            if (!empty($participantData)) {
                $participant->update($participantData);
            }
        });

        $participant->refresh();
        $participant->load(['person', 'edition']);

        return response()->json($this->format($participant));
    }

//    /**
//     * PATCH /api/records/participants/{participant}/confirm
//     */
//    public function confirm(Request $request, Participant $participant): JsonResponse
//    {
//        if ($participant->removed_at !== null) {
//            return response()->json([
//                'message' => 'Participant not found',
//            ], 404);
//        }
//
//        $data = $request->validate([
//            'presented' => ['required', 'boolean'],
//        ]);
//
//        $participant->update([
//            'presented_at' => $data['presented'] ? now() : null,
//        ]);
//
//        $participant->load(['person', 'edition']);
//
//        return response()->json($this->format($participant));
//    }

    /**
     * DELETE /api/records/participants/{participant}
     */
    public function destroy(Participant $participant): JsonResponse
    {
        if ($participant->removed_at === null) {
            $participant->update([
                'removed_at' => now(),
            ]);
        }

        return response()->json(null, 204);
    }

    /**
     * Format API response
     */
    private function format(Participant $participant): array
    {
        return [
            'id' => $participant->id,
            'edition_id' => $participant->edition_id,
            'people_id' => $participant->people_id,
            'name' => $participant->person?->name,
            'email' => $participant->person?->email,
            'phone' => $participant->person?->phone,
            'federal_code' => $participant->person?->federal_code,
            'presented_at' => $participant->presented_at,
            'presented' => $participant->presented_at !== null,
            'prizedraw_confirmation_at' => $participant->prizedraw_confirmation_at,
            'prizedraw_winner_at' => $participant->prizedraw_winner_at,
            'prizedraw_order' => $participant->prizedraw_order,
            'prizedraw_description' => $participant->prizedraw_description,
            'created_at' => $participant->created_at,
            'updated_at' => $participant->updated_at,
            'removed_at' => $participant->removed_at,
        ];
    }
}
