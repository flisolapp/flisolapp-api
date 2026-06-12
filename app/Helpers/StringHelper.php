<?php

namespace App\Helpers;

class StringHelper
{

    /**
     * Formats a person's name: proper casing and limited character count.
     *
     * @param string $name
     * @param int $maxCharacters
     * @return string
     */
    public static function prepareName(string $name, int $maxCharacters = 26): string
    {
        $exceptions = ['da', 'das', 'de', 'des', 'do', 'dos'];

        $formatted = array_map(function ($word) use ($exceptions) {
            return (strlen($word) > 2 && !in_array($word, $exceptions))
                ? ucfirst(strtolower($word))
                : strtolower($word);
        }, explode(' ', strtolower($name)));

        $name = implode(' ', $formatted);

        // Trim words from the end until length is within limit
        while (strlen($name) > $maxCharacters && count($formatted) > 1) {
            array_pop($formatted);
            $name = implode(' ', $formatted);
        }

        return $name;
    }


    /**
     * Splits a long name into two lines, breaking at the nearest space close to the desired length.
     *
     * @param string $text The original text to split.
     * @param int $near Preferred max length for the first line.
     * @return array [firstLine, secondLine|null]
     */
    public static function splitTextBySpace(string $text, int $near): array
    {
        if (mb_strlen($text) <= $near) {
            return [$text, null];
        }

        $before = mb_strrpos(mb_substr($text, 0, $near + 1), ' ');
        $after = mb_strpos($text, ' ', $near);

        if ($before === false && $after === false) {
            return [$text, null];
        }

        $splitPos = $before !== false ? $before : $after;
        $firstLine = trim(mb_substr($text, 0, $splitPos));
        $secondLine = trim(mb_substr($text, $splitPos));

        return [$firstLine, $secondLine];
    }

    /**
     * Extract the first name from the full name and capitalise it.
     *
     * Examples:
     *   "MARIA SILVA"  → "Maria"
     *   "joão pedro"   → "João"
     *   "Ana"          → "Ana"
     */
    public static function firstName($name): string
    {
        $first = explode(' ', trim($name))[0];

        return ucfirst(strtolower($first));
    }

}
