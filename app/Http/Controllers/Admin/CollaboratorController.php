<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CollaborationArea;
use App\Models\CollaborationShift;
use App\Models\Collaborator;
use App\Models\CollaboratorArea;
use App\Models\CollaboratorAvailability;
use App\Models\People;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CollaboratorController extends Controller
{
    /** GET /api/records/collaborators */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'edition_id' => ['nullable', 'integer', 'exists:editions,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'approved' => ['nullable', 'boolean'],
            'presented' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', 'string', 'in:id,name,email,phone,federal_code,approved,presented'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $perPage = (int)($data['per_page'] ?? 10);
        $sortBy = $data['sort_by'] ?? 'id';
        $sortDirection = $data['sort_direction'] ?? 'desc';

        $query = Collaborator::query()
            ->with([
                'person',
                'collaborationAreas:id,name',
                'collaborationShifts:id,name',
            ])
            ->join('people', 'people.id', '=', 'collaborators.people_id')
            ->select('collaborators.*')
            ->when(
                !empty($data['edition_id']),
                fn($q) => $q->where('collaborators.edition_id', $data['edition_id'])
            )
            ->when(
                array_key_exists('approved', $data),
                fn($q) => $data['approved']
                    ? $q->whereNotNull('collaborators.approved_at')
                    : $q->whereNull('collaborators.approved_at')
            )
            ->when(
                array_key_exists('presented', $data),
                fn($q) => $data['presented']
                    ? $q->whereNotNull('collaborators.presented_at')
                    : $q->whereNull('collaborators.presented_at')
            )
            ->when(
                !empty($data['search']),
                function ($q) use ($data) {
                    $search = trim($data['search']);

                    $q->where(function ($sub) use ($search) {
                        $sub->where('people.name', 'like', "%{$search}%")
                            ->orWhere('people.email', 'like', "%{$search}%")
                            ->orWhere('people.phone', 'like', "%{$search}%")
                            ->orWhere('people.federal_code', 'like', "%{$search}%")
                            ->orWhereHas('collaborationAreas', function ($areaQuery) use ($search) {
                                $areaQuery->where('name', 'like', "%{$search}%");
                            })
                            ->orWhereHas('collaborationShifts', function ($shiftQuery) use ($search) {
                                $shiftQuery->where('name', 'like', "%{$search}%");
                            });
                    });
                }
            );

        $this->applySorting($query, $sortBy, $sortDirection);

        $collaborators = $query->paginate($perPage);

        $collaborators->getCollection()->transform(
            fn(Collaborator $collaborator) => $this->format($collaborator)
        );

        return response()->json($collaborators);
    }

    /** GET /api/records/collaborators/{id} */
    public function show(Collaborator $collaborator): JsonResponse
    {
        $collaborator->load([
            'person',
            'collaborationAreas:id,name',
            'collaborationShifts:id,name',
        ]);

        return response()->json($this->format($collaborator));
    }

    /** POST /api/records/collaborators */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:121'],
            'phone' => ['required', 'string', 'max:20'],
            'federal_code' => ['nullable', 'string', 'max:20'],
            'edition_id' => ['required', 'exists:editions,id'],
            'areas' => ['nullable', 'array'],
            'areas.*' => ['integer', 'exists:collaboration_areas,id'],
            'shifts' => ['nullable', 'array'],
            'shifts.*' => ['integer', 'exists:collaboration_shifts,id'],
        ]);

        $collaborator = DB::transaction(function () use ($data) {
            $person = People::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'federal_code' => $data['federal_code'] ?? null,
                'use_free' => true,
            ]);

            $collaborator = Collaborator::create([
                'people_id' => $person->id,
                'edition_id' => $data['edition_id'],
                'approved_at' => null,
                'presented_at' => null,
            ]);

            foreach ($data['areas'] ?? [] as $areaId) {
                CollaboratorArea::create([
                    'collaborator_id' => $collaborator->id,
                    'collaboration_area_id' => $areaId,
                ]);
            }

            foreach ($data['shifts'] ?? [] as $shiftId) {
                CollaboratorAvailability::create([
                    'collaborator_id' => $collaborator->id,
                    'collaborator_shift_id' => $shiftId,
                ]);
            }

            return $collaborator->load([
                'person',
                'collaborationAreas:id,name',
                'collaborationShifts:id,name',
            ]);
        });

        return response()->json($this->format($collaborator), 201);
    }

    /** PUT/PATCH /api/records/collaborators/{id} */
    public function update(Request $request, Collaborator $collaborator): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'email' => ['sometimes', 'email', 'max:121'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'federal_code' => ['nullable', 'string', 'max:20'],
            'areas' => ['nullable', 'array'],
            'areas.*' => ['integer', 'exists:collaboration_areas,id'],
            'shifts' => ['nullable', 'array'],
            'shifts.*' => ['integer', 'exists:collaboration_shifts,id'],
        ]);

        DB::transaction(function () use ($data, $collaborator) {
            $personFields = [];

            if (array_key_exists('name', $data)) {
                $personFields['name'] = $data['name'];
            }

            if (array_key_exists('email', $data)) {
                $personFields['email'] = $data['email'];
            }

            if (array_key_exists('phone', $data)) {
                $personFields['phone'] = $data['phone'];
            }

            if (array_key_exists('federal_code', $data)) {
                $personFields['federal_code'] = $data['federal_code'];
            }

            if (!empty($personFields)) {
                $collaborator->person->update($personFields);
            }

            if (array_key_exists('areas', $data)) {
                CollaboratorArea::where('collaborator_id', $collaborator->id)->delete();

                foreach ($data['areas'] ?? [] as $areaId) {
                    CollaboratorArea::create([
                        'collaborator_id' => $collaborator->id,
                        'collaboration_area_id' => $areaId,
                    ]);
                }
            }

            if (array_key_exists('shifts', $data)) {
                CollaboratorAvailability::where('collaborator_id', $collaborator->id)->delete();

                foreach ($data['shifts'] ?? [] as $shiftId) {
                    CollaboratorAvailability::create([
                        'collaborator_id' => $collaborator->id,
                        'collaborator_shift_id' => $shiftId,
                    ]);
                }
            }
        });

        return response()->json(
            $this->format(
                $collaborator->load([
                    'person',
                    'collaborationAreas:id,name',
                    'collaborationShifts:id,name',
                ])
            )
        );
    }

    /** PATCH /api/records/collaborators/{id}/approve */
    public function approve(Request $request, Collaborator $collaborator): JsonResponse
    {
        $data = $request->validate([
            'approved' => ['required', 'boolean'],
        ]);

        $collaborator->update([
            'approved_at' => $data['approved'] ? now() : null,
        ]);

        return response()->json(
            $this->format(
                $collaborator->load([
                    'person',
                    'collaborationAreas:id,name',
                    'collaborationShifts:id,name',
                ])
            )
        );
    }

//    /** PATCH /api/records/collaborators/{id}/confirm */
//    public function confirm(Request $request, Collaborator $collaborator): JsonResponse
//    {
//        $data = $request->validate([
//            'presented' => ['required', 'boolean'],
//        ]);
//
//        $collaborator->update([
//            'presented_at' => $data['presented'] ? now() : null,
//        ]);
//
//        return response()->json(
//            $this->format(
//                $collaborator->load([
//                    'person',
//                    'collaborationAreas:id,name',
//                    'collaborationShifts:id,name',
//                ])
//            )
//        );
//    }

    /** DELETE /api/records/collaborators/{id} */
    public function destroy(Collaborator $collaborator): JsonResponse
    {
        $collaborator->delete();

        return response()->json(null, 204);
    }

    /** GET /api/records/collaborators/metadata */
    public function metadata(): JsonResponse
    {
        return response()->json([
            'areas' => CollaborationArea::query()
                ->whereNull('removed_at')
                ->orderBy('name')
                ->get(['id', 'name']),
            'shifts' => CollaborationShift::query()
                ->whereNull('removed_at')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    private function applySorting($query, string $sortBy, string $sortDirection): void
    {
        match ($sortBy) {
            'name' => $query->orderBy('people.name', $sortDirection),
            'email' => $query->orderBy('people.email', $sortDirection),
            'phone' => $query->orderBy('people.phone', $sortDirection),
            'federal_code' => $query->orderBy('people.federal_code', $sortDirection),
            'approved' => $query->orderByRaw(
                "CASE WHEN collaborators.approved_at IS NULL THEN 0 ELSE 1 END {$sortDirection}"
            ),
            'presented' => $query->orderByRaw(
                "CASE WHEN collaborators.presented_at IS NULL THEN 0 ELSE 1 END {$sortDirection}"
            ),
            default => $query->orderBy('collaborators.id', $sortDirection),
        };
    }

    private function format(Collaborator $c): array
    {
        return [
            'id' => $c->id,
            'edition_id' => $c->edition_id,
            'name' => $c->person?->name,
            'email' => $c->person?->email,
            'phone' => $c->person?->phone,
            'federal_code' => $c->person?->federal_code,
            'approved' => !is_null($c->approved_at),
            'approved_at' => optional($c->approved_at)?->toIso8601String(),
            'presented' => !is_null($c->presented_at),
            'presented_at' => optional($c->presented_at)?->toIso8601String(),
            'areas' => $c->collaborationAreas
                ->map(fn($area) => [
                    'id' => $area->id,
                    'name' => $area->name,
                ])
                ->values()
                ->all(),
            'shifts' => $c->collaborationShifts
                ->map(fn($shift) => [
                    'id' => $shift->id,
                    'name' => $shift->name,
                ])
                ->values()
                ->all(),
        ];
    }
}
