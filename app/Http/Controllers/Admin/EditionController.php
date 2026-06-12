<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EditionController extends Controller
{
    /** GET /api/admin/editions */
    public function index(): JsonResponse
    {
        $editions = Edition::orderByDesc('id')->get();

        return response()->json($editions);
    }

    /** GET /api/admin/editions/{edition} */
    public function show(Edition $edition): JsonResponse
    {
        return response()->json($edition);
    }

    /** POST /api/admin/editions */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'string', 'max:20'],
            'options' => ['nullable', 'array'],
            'active' => ['boolean'],
        ]);

        $edition = DB::transaction(function () use ($data) {
            if (!empty($data['active'])) {
                // Only one edition can be active at a time
                Edition::where('active', true)->update(['active' => false]);
            }

            if (isset($data['options'])) {
                $data['options'] = json_encode($data['options']);
            }

            return Edition::create($data);
        });

        return response()->json($edition, 201);
    }

    /** PUT/PATCH /api/admin/editions/{edition} */
    public function update(Request $request, Edition $edition): JsonResponse
    {
        $data = $request->validate([
            'year' => ['sometimes', 'string', 'max:20'],
            'options' => ['nullable', 'array'],
            'active' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $edition) {
            if (!empty($data['active'])) {
                Edition::where('active', true)
                    ->where('id', '!=', $edition->id)
                    ->update(['active' => false]);
            }

            if (isset($data['options'])) {
                $data['options'] = json_encode($data['options']);
            }

            $edition->update($data);
        });

        return response()->json($edition->fresh());
    }

    /** DELETE /api/admin/editions/{edition} */
    public function destroy(Edition $edition): JsonResponse
    {
        $edition->delete();

        return response()->json(null, 204);
    }
}
