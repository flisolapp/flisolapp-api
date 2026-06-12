<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EditionPlace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EditionPlaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'edition_id' => ['nullable', 'integer', 'exists:editions,id'],
            'kind' => ['nullable', 'string', 'max:30'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string', 'in:id,name,kind,floor,capacity,active,created_at,updated_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $query = EditionPlace::query()
            ->whereNull('removed_at')
            ->when(isset($data['edition_id']), fn($q) => $q->where('edition_id', $data['edition_id']))
            ->when(isset($data['kind']), fn($q) => $q->where('kind', $data['kind']))
            ->when(trim($data['search'] ?? '') !== '', function ($q) use ($data) {
                $search = trim($data['search']);
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('floor', 'like', "%{$search}%");
                });
            });

        $sortBy = $data['sort_by'] ?? 'floor';
        $sortDirection = $data['sort_direction'] ?? 'asc';

        $query->orderBy($sortBy, $sortDirection);

        if ($sortBy !== 'name') {
            $query->orderBy('name', 'asc');
        }

        return response()->json(
            $query->paginate($data['per_page'] ?? 50)->through(fn($place) => $this->format($place))
        );
    }

    public function show(EditionPlace $editionPlace): JsonResponse
    {
        if ($editionPlace->removed_at !== null) {
            return response()->json(['message' => 'Place not found'], 404);
        }

        return response()->json($this->format($editionPlace));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'edition_id' => ['required', 'integer', 'exists:editions,id'],
            'kind' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $place = EditionPlace::create($data);

        return response()->json($this->format($place), 201);
    }

    public function update(Request $request, EditionPlace $editionPlace): JsonResponse
    {
        if ($editionPlace->removed_at !== null) {
            return response()->json(['message' => 'Place not found'], 404);
        }

        $data = $request->validate([
            'edition_id' => ['sometimes', 'integer', 'exists:editions,id'],
            'kind' => ['sometimes', 'string', 'max:30'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $editionPlace->update($data);

        return response()->json($this->format($editionPlace->refresh()));
    }

    public function destroy(EditionPlace $editionPlace): JsonResponse
    {
        if ($editionPlace->removed_at === null) {
            $editionPlace->update(['removed_at' => now()]);
        }

        return response()->json(null, 204);
    }

    private function format(EditionPlace $place): array
    {
        return [
            'id' => $place->id,
            '_id' => $place->_id,
            'editionId' => $place->edition_id,
            'kind' => $place->kind,
            'name' => $place->name,
            'description' => $place->description,
            'floor' => $place->floor,
            'capacity' => $place->capacity,
            'active' => $place->active,
            'createdAt' => optional($place->created_at)?->toISOString(),
            'updatedAt' => optional($place->updated_at)?->toISOString(),
        ];
    }
}
