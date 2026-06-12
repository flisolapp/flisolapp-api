<?php

namespace App\Helpers;

use App\Models\Talk;
use Illuminate\Support\Collection;

class TalkHelper
{

    /**
     * Get talks with edition, subject, and speaker details.
     *
     * Example usage:
     * ```php
     * $allTalks = TalkHelper::getTalks();
     * $talks2024 = TalkHelper::getTalks(2024);
     * ```
     *
     * @param int|null $editionYear Optional. Filter by edition year.
     * @return Collection
     */
    public static function getAll(?int $editionYear = null)
    {
        $query = Talk::with(['edition', 'talkSubject', 'speakers.person'])
            ->where('id', '<>', 35)
            ->whereNull('removed_at');

        if ($editionYear !== null) {
            $query->whereHas('edition', function ($q) use ($editionYear) {
                $q->where('year', $editionYear);
            });
        }

        return $query->get()
            ->flatMap(function ($talk) {
                return $talk->speakers->map(function ($speakerTalk) use ($talk) {
                    $person = $speakerTalk->person;

                    return [
                        'year' => optional($talk->edition)->year,
                        'talk_subject' => optional($talk->talkSubject)->name,
                        'talk_id' => $talk->id,
                        'title' => $talk->title,
                        'description' => $talk->description,
                        'shift' => $talk->shift === 'M' ? 'Manhã' : 'Tarde',
                        'kind' => $talk->kind === 'O' ? 'Oficina' : 'Palestra',
                        'people_id' => optional($person)->id,
                        'name' => optional($person)->name,
                        'email' => optional($person)->email,
                        'phone' => optional($person)->phone,
                    ];
                });
            })
            ->sortByDesc('year')
            ->sortBy([
                fn($item) => $item['talk_subject'],
                fn($item) => $item['title'],
            ])
            ->values();
    }

}
