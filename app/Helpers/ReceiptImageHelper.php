<?php

namespace App\Helpers;

use ArPHP\I18N\Arabic;

class ReceiptImageHelper
{
    public static function createArabicImageForPrinter(string $text, string $fileName, int $fontSize = 16, int $padding = 5, $width = null)
    {
        // Shape Arabic text
        $Arabic = new Arabic('Glyphs');
        $shapedText = $Arabic->utf8Glyphs($text);

        $fontPath = public_path('fonts/Amiri-Regular.ttf');

        // Get bounding box
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $shapedText);
        $textWidth  = abs($bbox[2] - $bbox[0]);
        $textHeight = abs($bbox[7] - $bbox[1]);

        // Dynamic image size

        if (is_null($width)) {
            $width  = $textWidth + $padding * 2;
        }

        $height = $textHeight + $padding * 2; // Extra padding for descenders

        $im = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);
        imagefilledrectangle($im, 0, 0, $width, $height, $white);

        // Adjust Y to remove bottom space
        $x = $padding;
        $y = $padding + ($fontSize - 8); // top padding + text height

        imagettftext($im, $fontSize, 0, $x, $y, $black, $fontPath, $shapedText);

        imagepng($im, $fileName);
        imagedestroy($im);

        return $fileName;
    }

    // public static function createArabicImageForPrinter(string $text, string $fileName, int $fontSize = 20, int $printerWidth = 384, int $padding = 5)
    // {
    //     $Arabic = new \ArPHP\I18N\Arabic('Glyphs');
    //     $shapedText = $Arabic->utf8Glyphs($text);

    //     $fontPath = public_path('fonts/Amiri-Regular.ttf');

    //     // Create temporary image to calculate text width
    //     $bbox = imagettfbbox($fontSize, 0, $fontPath, $shapedText);
    //     $textWidth = abs($bbox[2] - $bbox[0]);
    //     $textHeight = abs($bbox[7] - $bbox[1]);

    //     // Scale to fit printer width
    //     $scale = ($printerWidth - $padding * 2) / $textWidth;
    //     $imgWidth = $printerWidth;
    //     $imgHeight = intval($textHeight * $scale) + $padding * 2;

    //     $im = imagecreatetruecolor($imgWidth, $imgHeight);
    //     $white = imagecolorallocate($im, 255, 255, 255);
    //     $black = imagecolorallocate($im, 0, 0, 0);
    //     imagefill($im, 0, 0, $white);

    //     // Render scaled text
    //     imagettftext($im, $fontSize * $scale, 0, $padding, $imgHeight - $padding, $black, $fontPath, $shapedText);

    //     // Convert to 1-bit black & white
    //     imagetruecolortopalette($im, true, 2);
    //     imagepng($im, $fileName);
    //     imagedestroy($im);

    //     return $fileName;
    // }
}
