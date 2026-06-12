<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use Illuminate\Http\JsonResponse;

class EditionController extends Controller
{
    /**
     * Retrieve all available editions.
     *
     * Returns all non-removed editions ordered by year in descending order.
     * The response contains only the minimal fields required by the client
     * to identify and select an edition.
     *
     * Typical usage:
     * - Populate edition selectors (dropdowns, filters)
     * - Resolve edition_id before submitting forms
     *
     * Response structure:
     * - data: array of editions (id, year, active)
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $editions = Edition::orderByDesc('year')
            ->whereNull('removed_at')
            ->get(['id', 'year', 'active']);

        return response()->json([
            'data' => $editions,
        ]);
    }

    /**
     * Retrieve the currently active edition.
     *
     * Returns the single edition marked as active. This endpoint is a
     * convenience shortcut to avoid clients needing to filter the full list.
     *
     * Behavior:
     * - Returns the active edition when found
     * - Returns 404 when no active edition exists
     *
     * Response structure:
     * - data: edition object (id, year, active)
     * - message: error description when not found
     *
     * @return JsonResponse
     */
    public function active(): JsonResponse
    {
        $edition = Edition::where('active', true)
            ->whereNull('removed_at')
            ->first(['id', 'year', 'active']);

        if (!$edition) {
            return response()->json([
                'message' => 'Nenhuma edição ativa encontrada.',
            ], 404);
        }

        return response()->json([
            'data' => $edition,
        ]);
    }
}
