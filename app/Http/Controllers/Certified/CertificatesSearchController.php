<?php

namespace App\Http\Controllers\Certified;

use App\Helpers\TermHelper;
use App\Http\Controllers\Controller;
use App\Models\PeopleCertificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CertificatesSearchController extends Controller
{
    /**
     * Search released certificates by public code or person email.
     *
     * Receives a search term, normalizes and validates it, then searches
     * non-removed certificate records using one of the following criteria:
     * - Exact certificate public code
     * - Exact email of the related person
     *
     * GET /api/certified/{term}
     *
     * Possible responses:
     * - 200: One or more certificates were found
     * - 400: The informed search term is invalid
     * - 404: No certificates matched the informed term
     *
     * @param string $term Search term — certificate code or email address.
     */
    public function execute(string $term): JsonResponse
    {
        try {
            $term = TermHelper::prepare($term);
            Log::info('Certificate search: ' . $term);
        } catch (InvalidArgumentException $e) {
            Log::warning('Invalid term used in certificate search: ' . $e->getMessage());

            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }

        $items = PeopleCertificate::with(['person', 'edition'])
            ->where(function ($query) use ($term) {
                $query->where('code', $term)
                    ->orWhereHas('person', fn($q) => $q
                        ->withTrashed()
                        ->where('email', $term));
            })
            ->whereNull('removed_at')
            ->orderByDesc('edition_id')
            ->orderBy('name')
            ->get();

        if ($items->isEmpty()) {
            return response()->json([], 404);
        }

        $list = $items->map(function ($item) {
            $enjoyedAs = null;

            if (!is_null($item->organizer_id)) {
                $enjoyedAs = 'Organizer';
            } elseif (!is_null($item->collaborator_id)) {
                $enjoyedAs = 'Collaborator';
            } elseif (!is_null($item->talk_id)) {
                $enjoyedAs = 'Speaker';
            } elseif (!is_null($item->participant_id)) {
                $enjoyedAs = 'Participant';
            }

            $unit = null;

            if (!empty($item->edition->options) && is_object($item->edition->options)) {
                $unit = $item->edition->options->unit ?? null;
            }

            return [
                'edition' => $item->edition->year,
                'unit' => $unit,
                'name' => $item->name,
                'enjoyedAs' => $enjoyedAs,
                'code' => $item->code,
            ];
        });

        return response()->json($list);
    }
}
