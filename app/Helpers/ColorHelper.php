<?php

namespace App\Helpers;

class ColorHelper
{

    /**
     * // Example usage
     * $color = "#FE8200";
     * $rgb = hexToRgb($color);
     *
     * if ($rgb) {
     * print_r($rgb);
     * } else {
     * echo "Invalid color format";
     * }
     *
     * @param string $hex
     * @return object|bool
     */
    public static function hexToRgb(string $hex): object|bool
    {
        // Remove the hash (#) if it's present
        $hex = str_replace("#", "", $hex);

        // Ensure the hex string length is exactly 6 characters
        if (strlen($hex) !== 6) {
            return false;
        }

        // Convert hex to decimal values
        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));

        // Return the colors as an associative array
        return (object)['red' => $red, 'green' => $green, 'blue' => $blue];
    }

    // Example usage
    // echo rgbToHex(254, 130, 0);  // Outputs: #fe8200
    public static function rgbToHex(int $red, int $green, int $blue): string
    {
        // Ensure the RGB values are within the 0-255 range
        $red = max(0, min(255, $red));
        $green = max(0, min(255, $green));
        $blue = max(0, min(255, $blue));

        // Convert the integers to hexadecimal strings and concatenate them with a leading '#'
        return sprintf('#%02x%02x%02x', $red, $green, $blue);
    }

}
