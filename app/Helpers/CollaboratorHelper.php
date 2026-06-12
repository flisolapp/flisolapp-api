<?php

namespace App\Helpers;

use App\Models\Collaborator;
use Illuminate\Support\Collection;

class CollaboratorHelper
{

    /**
     * Get collaborators with areas and availabilities.
     *
     * Example usage:
     * ```php
     * $allCollaborators = CollaboratorHelper::getCollaborators();
     * $collaborators2024 = CollaboratorHelper::getCollaborators(2024);
     * ```
     *
     * @param int|null $editionYear Optional. If provided, filters collaborators by edition year.
     * @return Collection
     */
    public static function getAll(?int $editionYear = null)
    {
        $query = Collaborator::with([
            'person',
            'edition',
            'areas.area',
            'availabilities.shift'
        ]);

        if ($editionYear !== null) {
            $query->whereHas('edition', function ($q) use ($editionYear) {
                $q->where('year', $editionYear);
            });
        }

        return $query->get()
            ->sortByDesc(fn($c) => $c->edition->year ?? 0)
            ->sortBy(fn($c) => $c->person->name ?? '')
            ->map(function ($c) {
                return [
                    'year' => optional($c->edition)->year,
                    'name' => optional($c->person)->name,
                    'email' => optional($c->person)->email,
                    'phone' => optional($c->person)->phone,
                    'student_place' => optional($c->person)->student_place,
                    'areas' => $c->areas->pluck('area.name')->filter()->implode('; '),
                    'availabilities' => $c->availabilities->pluck('shift.name')->filter()->implode('; '),
                ];
            })
            ->values();
    }

}
