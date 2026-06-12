<?php

namespace App\Helpers;

use InvalidArgumentException;

/**
 * TermHelper Class
 *
 * This class provides functionality to process and validate search terms.
 * It includes methods to prepare a search term by trimming whitespace, converting emails to lowercase,
 * and sanitizing input to ensure it adheres to specific formats such as email addresses or custom code patterns.
 */
class TermHelper
{

    /**
     * Prepares a given search term by trimming it, converting it to lowercase if it's an email,
     * and removing non-alphanumeric characters if it's not an email.
     *
     * The method processes a search term as follows:
     * 1. Trims any leading and trailing whitespace from the term.
     * 2. Checks if the term is in a valid email format.
     * 3. If the term is an email, it converts the term to lowercase for consistency.
     * 4. If the term is not an email, it removes all characters except numbers and unaccented letters.
     * 5. Throws an exception if the term is null, an empty string after trimming, or doesn't match the expected patterns.
     *
     * @param string|null $term The search term to be prepared. Can be a string or null.
     * @return string The processed term: if it's an email, it's trimmed and converted to lowercase;
     *                if it's not an email, it's trimmed and cleansed of non-alphanumeric characters.
     *                Throws an exception if the term is null, an empty string, or doesn't match the expected formats.
     *
     * @throws InvalidArgumentException If the term is invalid, indicating that a non-empty search term is required.
     */
    public static function prepare(string|null $term): string
    {
        // Initial check to ensure the term is not null or an empty string.
        // This validation is crucial to avoid processing invalid input.
        if ($term === null || trim($term) === '') {
            throw new InvalidArgumentException('The term is invalid to search. It is required.', -1);
        }

        // Local function to check if the term is a valid email format.
        // This validation is performed using a regular expression.
        $isEmail = function (string $term): bool {
            return (bool)preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $term);
        };

        // Trimming the term and extracting the last segment after the last '/' character.
        // Useful for processing terms that might be part of a URL or path.
        $processedTerm = str_contains($term, '/') ? trim(substr($term, strrpos($term, '/') + 1)) : trim($term);

        // Additional check to ensure the processed term is not an empty string.
        if ($processedTerm === '') {
            throw new InvalidArgumentException('The term is invalid to search.', -2);
        }

        // Standardize the term by converting it to lowercase if it's an email.
        // If not an email, sanitize by removing non-alphanumeric characters.
        if ($isEmail($processedTerm)) {
            $processedTerm = strtolower($processedTerm);
        } else {
            $processedTerm = preg_replace('/[^a-zA-Z0-9]/', '', $processedTerm);
        }

        // Final validation to confirm the term is either a valid email or code.
        if (!$isEmail($processedTerm) && !self::isCode($processedTerm)) {
            throw new InvalidArgumentException('The term is invalid to search. Must be an e-mail or certificate\'s code.', -3);
        }

        return $processedTerm;
    }

    /**
     * Checks if a given string is a valid code.
     *
     * The method uses a regular expression to check if the string is a code, which is defined as:
     * - A string containing 15 to 18 alphanumeric characters.
     *
     * @param string $code The string to be checked as a code.
     * @return bool Returns true if the string matches the code pattern, false otherwise.
     */
    public static function isCode(string $code): bool
    {
        return (bool)preg_match('/^[A-Za-z0-9]{15,18}$/', $code);
    }

}
