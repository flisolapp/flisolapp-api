<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\People;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizerController extends Controller
{
    /**
     * GET /api/records/organizers
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

        $organizers = Organizer::query()
            ->select('organizers.*')
            ->with(['person', 'edition'])
            ->join('people', 'people.id', '=', 'organizers.people_id')
            ->whereNull('organizers.removed_at')
            ->whereNull('people.removed_at')
            ->when(isset($data['edition_id']), function ($q) use ($data) {
                $q->where('organizers.edition_id', $data['edition_id']);
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('people.name', 'like', '%' . $search . '%')
                        ->orWhere('people.email', 'like', '%' . $search . '%')
                        ->orWhere('people.phone', 'like', '%' . $search . '%')
                        ->orWhere('people.federal_code', 'like', '%' . $search . '%')
                        ->orWhere('organizers.id', 'like', '%' . $search . '%');
                });
            });

        switch ($sortBy) {
            case 'name':
                $organizers->orderBy('people.name', $sortDirection);
                break;

            case 'email':
                $organizers->orderBy('people.email', $sortDirection);
                break;

            case 'phone':
                $organizers->orderBy('people.phone', $sortDirection);
                break;

            case 'federal_code':
                $organizers->orderBy('people.federal_code', $sortDirection);
                break;

//            case 'presented':
//                $organizers->orderByRaw(
//                    'CASE WHEN organizers.presented_at IS NULL THEN 0 ELSE 1 END ' . $sortDirection
//                );
//                break;

            case 'created_at':
                $organizers->orderBy('organizers.created_at', $sortDirection);
                break;

            case 'updated_at':
                $organizers->orderBy('organizers.updated_at', $sortDirection);
                break;

            case 'id':
            default:
                $organizers->orderBy('organizers.id', $sortDirection);
                break;
        }

        $organizers = $organizers
            ->paginate($perPage)
            ->through(function (Organizer $organizer) {
                return $this->format($organizer);
            });

        return response()->json($organizers);
    }

    /**
     * GET /api/records/organizers/{organizer}
     */
    public function show(Organizer $organizer): JsonResponse
    {
        if ($organizer->removed_at !== null) {
            return response()->json([
                'message' => 'Organizer not found',
            ], 404);
        }

        $organizer->load(['person', 'edition']);

        if (!$organizer->person || $organizer->person->removed_at !== null) {
            return response()->json([
                'message' => 'Organizer not found',
            ], 404);
        }

        return response()->json($this->format($organizer));
    }

    /**
     * POST /api/records/organizers
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

        $organizer = DB::transaction(function () use ($data) {
            $person = People::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'federal_code' => $data['federal_code'] ?? null,
                'use_free' => false,
            ]);

            return Organizer::create([
                'edition_id' => $data['edition_id'],
                'people_id' => $person->id,
            ]);
        });

        $organizer->load(['person', 'edition']);

        return response()->json($this->format($organizer), 201);
    }

    /**
     * PUT/PATCH /api/records/organizers/{organizer}
     */
    public function update(Request $request, Organizer $organizer): JsonResponse
    {
        if ($organizer->removed_at !== null) {
            return response()->json([
                'message' => 'Organizer not found',
            ], 404);
        }

        $organizer->load(['person', 'edition']);

        if (!$organizer->person || $organizer->person->removed_at !== null) {
            return response()->json([
                'message' => 'Organizer not found',
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

        DB::transaction(function () use ($organizer, $data) {
            $personData = [];
            $organizerData = [];

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
                $organizer->person->update($personData);
            }

            if (array_key_exists('edition_id', $data)) {
                $organizerData['edition_id'] = $data['edition_id'];
            }

            if (array_key_exists('presented_at', $data)) {
                $organizerData['presented_at'] = $data['presented_at'];
            }

            if (!empty($organizerData)) {
                $organizer->update($organizerData);
            }
        });

        $organizer->refresh();
        $organizer->load(['person', 'edition']);

        return response()->json($this->format($organizer));
    }

//    /**
//     * PATCH /api/records/organizers/{organizer}/confirm
//     */
//    public function confirm(Request $request, Organizer $organizer): JsonResponse
//    {
//        if ($organizer->removed_at !== null) {
//            return response()->json([
//                'message' => 'Organizer not found',
//            ], 404);
//        }
//
//        $data = $request->validate([
//            'presented' => ['required', 'boolean'],
//        ]);
//
//        $organizer->update([
//            'presented_at' => $data['presented'] ? now() : null,
//        ]);
//
//        $organizer->load(['person', 'edition']);
//
//        return response()->json($this->format($organizer));
//    }

    /**
     * DELETE /api/records/organizers/{organizer}
     */
    public function destroy(Organizer $organizer): JsonResponse
    {
        if ($organizer->removed_at === null) {
            $organizer->update([
                'removed_at' => now(),
            ]);
        }

        return response()->json(null, 204);
    }

    private function format(Organizer $organizer): array
    {
        return [
            'id' => $organizer->id,
            'name' => $organizer->person?->name,
            'email' => $organizer->person?->email,
            'phone' => $organizer->person?->phone,
            'federalCode' => $organizer->person?->federal_code,
//            'presented' => $organizer->presented_at !== null,
            'editionId' => $organizer->edition_id,
            'presentedAt' => optional($organizer->presented_at)?->toISOString(),
            'createdAt' => optional($organizer->created_at)?->toISOString(),
            'updatedAt' => optional($organizer->updated_at)?->toISOString(),
        ];
    }
}
