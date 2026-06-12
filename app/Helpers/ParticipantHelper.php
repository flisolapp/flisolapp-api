<?php

namespace App\Helpers;

use App\Models\Participant;
use Illuminate\Support\Collection;

class ParticipantHelper
{

    /**
     * Get participants with people and edition information.
     *
     * Example usage:
     * ```php
     * $all = ParticipantHelper::getParticipants();
     * $year2024 = ParticipantHelper::getParticipants(2024);
     * ```
     *
     * @param int|null $editionYear Optional. Filter by edition year.
     * @return Collection
     */
    public static function getAll(?int $editionYear = null)
    {
        $query = Participant::with(['person', 'edition']);

        if ($editionYear !== null) {
            $query->whereHas('edition', function ($q) use ($editionYear) {
                $q->where('year', $editionYear);
            });
        }

        return $query->get()
            ->map(function ($pt) {
                return [
                    'year' => optional($pt->edition)->year,
                    'participant_id' => $pt->id,
                    'people_id' => optional($pt->person)->id,
                    'name' => optional($pt->person)->name,
                    'email' => optional($pt->person)->email,
                    'phone' => optional($pt->person)->phone,
                    'presented_at' => $pt->presented_at,
                ];
            })
            ->values();
    }

    /**
     * Get participants who have presented (presented_at not null).
     *
     * Example usage:
     * ```php
     * $all = PresentedParticipantHelper::getPresentedParticipants();
     * $year2024 = PresentedParticipantHelper::getPresentedParticipants(2024);
     * ```
     *
     * @param int|null $editionYear Optional. Filter by edition year.
     * @return Collection
     */
    public static function getPresented(?int $editionYear = null)
    {
        return self::getParticipants($editionYear)
            ->filter(fn($pt) => !is_null($pt['presented_at']))
            ->values();
    }

}
