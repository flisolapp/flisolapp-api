<?php

namespace App\Helpers;

use App\Dto\QrCodeImage;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use GdImage;

/**
 * CertificateHelper Class
 *
 * This class provides utilities for generating and manipulating certificate images.
 * It includes methods for adding various elements to a certificate image such as federal codes,
 * QR codes, verification URLs, and generating alphanumeric codes. It also handles the
 * conversion of image data to a string format.
 */
class CertificateHelper
{

    /**
     * Adds a federal code to an image at a specified position.
     *
     * @param GdImage $image The image resource to modify.
     * @param string $federalCodeName The name of the federal code.
     * @param string $federalCode The federal code value.
     * @param int $x The X-coordinate for the text placement.
     * @param int $y The Y-coordinate for the text placement.
     * @return void
     */
    public static function addFederalCode(GdImage $image, string $federalCodeName, string $federalCode, int $x, int $y): void
    {
        $font = resource_path('fonts/SpaceMono-Regular.ttf');
        $text = $federalCodeName . ': ' . $federalCode;

        // Detect bounding box
        $bbox = imageftbbox(10, 0, $font, $text);

        $color = imagecolorallocatealpha($image, 255, 255, 255, 30);
        imagefilledrectangle($image, 8, 8, $bbox[2] + $x + 2, $y + 5, $color);

        $color = imagecolorallocate($image, 0, 0, 0);
        imagefttext($image, 10, 0, $x, $y, $color, $font, $text);
    }

    /**
     * Adds a verification URL to an image at a specified position.
     *
     * @param GdImage $image The image resource to modify.
     * @param string $value The URL to be added to the image.
     * @param int $x The X-coordinate for the URL placement.
     * @param int $y The Y-coordinate for the URL placement.
     * @return void
     */
    public static function addCodeVerificationUrl(GdImage $image, string $value, int $x, int $y): void
    {
        $font = resource_path('fonts/SpaceMono-Regular.ttf');
        $text = 'Verify at: ' . $value;

        // Detect bounding box
        $bbox = imageftbbox(10, 90, $font, $text);

        $color = imagecolorallocatealpha($image, 255, 255, 255, 30);
        imagefilledrectangle($image, $x - 16, 8, $x + 5, $y + ($bbox[3] * -1) + 16, $color);

        $color = imagecolorallocate($image, 0, 0, 0);
        imagefttext($image, 10, 90, $x, $y + ($bbox[3] * -1) + 12, $color, $font, $text);
    }

    /**
     * Encodes a number into a short alphanumeric version, or decodes such a version back to a number.
     *
     * Translated any number up to 9007199254740992
     * to a shorter version in letters e.g.:
     * 9007199254740989 --> PpQXn7COf
     *
     * specifiying the second argument true, it will
     * translate back e.g.:
     * PpQXn7COf --> 9007199254740989
     *
     * If you want the alphaID to be at least 3 letter long, use the
     * $pad_up = 3 argument
     *
     * In most cases this is better than totally random ID generators
     * because this can easily avoid duplicate ID's.
     * For example if you correlate the alpha ID to an auto incrementing ID
     * in your database, you're done.
     *
     * The reverse is done because it makes it slightly more cryptic,
     * but it also makes it easier to spread lots of IDs in different
     * directories on your filesystem. Example:
     * $part1 = substr($alpha_id,0,1);
     * $part2 = substr($alpha_id,1,1);
     * $part3 = substr($alpha_id,2,strlen($alpha_id));
     * $destindir = "/".$part1."/".$part2."/".$part3;
     * // by reversing, directories are more evenly spread out. The
     * // first 26 directories already occupy 26 main levels
     *
     * more info on limitation:
     * - http://blade.nagaokaut.ac.jp/cgi-bin/scat.rb/ruby/ruby-talk/165372
     *
     * if you really need this for bigger numbers you probably have to look
     * at things like: http://theserverpages.com/php/manual/en/ref.bc.php
     * or: http://theserverpages.com/php/manual/en/ref.gmp.php
     * but I haven't really dugg into this. If you have more info on those
     * matters feel free to leave a comment.
     *
     * The following code block can be utilized by PEAR's Testing_DocTest
     * <code>
     * // Input //
     * $number_in = 2188847690240;
     * $alpha_in  = "SpQXn7Cb";
     *
     * // Execute //
     * $alpha_out  = alphaID($number_in, false, 8);
     * $number_out = alphaID($alpha_in, true, 8);
     *
     * if ($number_in != $number_out) {
     *   echo "Conversion failure, ".$alpha_in." returns ".$number_out." instead of the ";
     *   echo "desired: ".$number_in."\n";
     * }
     * if ($alpha_in != $alpha_out) {
     *   echo "Conversion failure, ".$number_in." returns ".$alpha_out." instead of the ";
     *   echo "desired: ".$alpha_in."\n";
     * }
     *
     * // Show //
     * echo $number_out." => ".$alpha_out."\n";
     * echo $alpha_in." => ".$number_out."\n";
     * echo alphaID(238328, false)." => ".alphaID(alphaID(238328, false), true)."\n";
     *
     * // expects:
     * // 2188847690240 => SpQXn7Cb
     * // SpQXn7Cb => 2188847690240
     * // aaab => 238328
     *
     * </code>
     *
     * @param mixed $in Input string or number to be translated.
     * @param boolean $to_num Specifies whether to translate back to a number.
     * @param mixed $pad_up If specified, pads the result up to a certain length.
     * @param string $pass_key An optional password for added security. Supplying a password makes it harder to calculate the original ID.
     * @return mixed Translated string or number.
     */
    private static function alphaID($in, $to_num = false, $pad_up = false, $pass_key = null)
    {
        $out = '';
        $index = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $base = strlen($index);

        if ($pass_key !== null) {
            // Although this function's purpose is to just make the
            // ID short - and not so much secure,
            // with this patch by Simon Franz (http://blog.snaky.org/)
            // you can optionally supply a password to make it harder
            // to calculate the corresponding numeric ID

            for ($n = 0; $n < strlen($index); $n++) {
                $i[] = substr($index, $n, 1);
            }

            $pass_hash = hash('sha256', $pass_key);
            $pass_hash = (strlen($pass_hash) < strlen($index) ? hash('sha512', $pass_key) : $pass_hash);

            for ($n = 0; $n < strlen($index); $n++) {
                $p[] = substr($pass_hash, $n, 1);
            }

            array_multisort($p, SORT_DESC, $i);
            $index = implode($i);
        }

        if ($to_num) {
            // Digital number  <<--  alphabet letter code
            $len = strlen($in) - 1;

            for ($t = $len; $t >= 0; $t--) {
                $bcp = bcpow($base, $len - $t);
                $out = $out + strpos($index, substr($in, $t, 1)) * $bcp;
            }

            if (is_numeric($pad_up)) {
                $pad_up--;

                if ($pad_up > 0) {
                    $out -= pow($base, $pad_up);
                }
            }
        } else {
            // Digital number  -->>  alphabet letter code
            if (is_numeric($pad_up)) {
                $pad_up--;

                if ($pad_up > 0) {
                    $in += pow($base, $pad_up);
                }
            }

            for ($t = ($in != 0 ? floor(log($in, $base)) : 0); $t >= 0; $t--) {
                $bcp = bcpow($base, $t);
                $a = floor($in / $bcp) % $base;
                $out = $out . substr($index, $a, 1);
                $in = $in - ($a * $bcp);
            }
        }

        return $out;
    }

    /**
     * Generates a unique alphanumeric code.
     *
     * @return string The generated code.
     */
    public static function generateCode(): string
    {
        $sid = uniqid();
        $id = collect(str_split($sid))
            ->map(fn($char) => is_numeric($char) ? $char : ord(strtolower($char)) - 96)
            ->implode('');

        $shuffled = str_shuffle($id);
        $id = $shuffled . str_shuffle($shuffled);

        return self::alphaID($id);
    }

    /**
     * Generates a QR code image from a given value.
     *
     * @param string $value The value to encode into the QR code.
     * @return QrCodeImage|null The QR code image or null if the value is null.
     */
    private static function generateQrCode($value): ?QrCodeImage
    {
        if (is_null($value)) {
            return null;
        }

        $options = new QROptions();
        $options->version = 10;
        $options->outputInterface = QRGdImagePNG::class;
        $options->eccLevel = EccLevel::H;
        $options->scale = 5;
        $options->outputBase64 = false;

        $file = tmpfile();
        $filePath = stream_get_meta_data($file)['uri'];
        (new QRCode($options))->render($value, $filePath);
        list($width, $height) = getimagesize($filePath);
        $image = @imagecreatefrompng($filePath);

        // Remove file
        unlink($filePath);

        $qrCodeImage = new QrCodeImage();
        $qrCodeImage->setWidth($width);
        $qrCodeImage->setHeight($height);
        $qrCodeImage->setImage($image);

        return $qrCodeImage;
    }

    /**
     * Adds a QR code to an image at a specified position.
     *
     * @param GdImage $image The image resource to modify.
     * @param string $value The value to encode into the QR code.
     * @param int $x The X-coordinate for the QR code placement.
     * @param int $y The Y-coordinate for the QR code placement.
     * @return void
     */
    public static function addQrCode(GdImage $image, string $value, int $x, int $y): void
    {
        $color = imagecolorallocatealpha($image, 255, 255, 255, 50);
        imagefilledrectangle($image, $x + 13, $y + 13, $x + 311, $y + 311, $color);

        $qrCodeImage = self::generateQrCode($value);

        imagecopymerge($image, $qrCodeImage->getImage(), $x, $y, 0, 0, $qrCodeImage->getWidth(), //
            $qrCodeImage->getHeight(), 100);
    }

    /**
     * Converts the image data into a string format.
     *
     * @param GdImage $image The image resource to convert.
     * @return string The image data as a string or false on failure.
     */
    public static function getData(GdImage $image): ?string
    {
        ob_start();
        $success = imagepng($image);
        $data = ob_get_clean();

        imagedestroy($image);

        return $success && $data !== false ? $data : null;
    }

}
