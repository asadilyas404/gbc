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

    public static function createMixedRowImage(
        string $leftText,
        string $centerText,
        string $arabicPngPath,
        string $outputPath,
        int $paperDots = 576,
        int $rowHeight = 64,     // ↑ more height
        int $padding = 8,
        int $fontSize = 26,      // ↑ bigger text
        bool $bold = true
    ): string {
        $w = $paperDots; $h = $rowHeight;

        $im = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);
        imagefilledrectangle($im, 0, 0, $w, $h, $white);

        // Mono font looks like receipt printers
        $font = public_path('fonts/Amiri-Regular.ttf');

        $arabic = imagecreatefrompng($arabicPngPath);
        $arabicW = imagesx($arabic);
        $arabicH = imagesy($arabic);

        $arabicX = max($padding, $w - $arabicW - $padding);
        $arabicY = max(0, intdiv($h - $arabicH, 2));
        imagecopy($im, $arabic, $arabicX, $arabicY, 0, 0, $arabicW, $arabicH);

        $drawText = function($text, $x, $y) use ($im, $fontSize, $font, $black, $bold) {
            imagettftext($im, $fontSize, 0, $x, $y, $black, $font, $text);
            if ($bold) {
                // fake bold: draw again with 1px offset
                imagettftext($im, $fontSize, 0, $x + 1, $y, $black, $font, $text);
            }
        };

        // Left text baseline
        $leftBox = imagettfbbox($fontSize, 0, $font, $leftText);
        $leftH = abs($leftBox[7] - $leftBox[1]);
        $baseY = intdiv($h + $leftH, 2) - 2;
        $drawText($leftText, $padding, $baseY);

        // Center text inside the area before arabic image
        $centerBox = imagettfbbox($fontSize, 0, $font, $centerText);
        $centerW = abs($centerBox[2] - $centerBox[0]);
        $centerH = abs($centerBox[7] - $centerBox[1]);

        $availRight = max($padding, $arabicX - $padding);
        $centerX = max($padding, intdiv(($availRight - $centerW), 2));
        $centerY = intdiv($h + $centerH, 2) - 2;

        $drawText($centerText, $centerX, $centerY);

        if (!is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0755, true);
        }
        imagepng($im, $outputPath);

        imagedestroy($arabic);
        imagedestroy($im);

        return $outputPath;
    }

    public static function createFullRowImage(
    string $leftText,
    string $centerText,
    string $rightArabicText,
    string $outputPath,
    int $paperDots = 576,  // 80mm printable width
    int $rowHeight = 56,
    int $padding = 8,
    int $latinFontSize = 24,
    int $arabicFontSize = 22,
    bool $bold = true
): string {
    $font = public_path('fonts/Amiri-Regular.ttf');
    if (!file_exists($font)) {
        throw new \Exception("Missing font: $font");
    }

    $im = imagecreatetruecolor($paperDots, $rowHeight);
    $white = imagecolorallocate($im, 255, 255, 255);
    $black = imagecolorallocate($im, 0, 0, 0);
    imagefilledrectangle($im, 0, 0, $paperDots, $rowHeight, $white);

    // Shape Arabic
    $Arabic = new Arabic('Glyphs');
    $rightShaped = $Arabic->utf8Glyphs($rightArabicText);

    $draw = function(string $txt, int $size, int $x, int $y) use ($im, $font, $black, $bold) {
        imagettftext($im, $size, 0, $x, $y, $black, $font, $txt);
        if ($bold) imagettftext($im, $size, 0, $x + 1, $y, $black, $font, $txt);
    };

    $baselineY = function(string $txt, int $size) use ($font, $rowHeight): int {
        $box = imagettfbbox($size, 0, $font, $txt);
        $h = abs($box[7] - $box[1]);
        return intdiv($rowHeight + $h, 2) - 2;
    };

    // Measure Arabic width (use shaped text)
    $rBox = imagettfbbox($arabicFontSize, 0, $font, $rightShaped);
    $rW = abs($rBox[2] - $rBox[0]);

    // Right position
    $rightX = max($padding, $paperDots - $rW - $padding);
    $rightY = $baselineY($rightShaped, $arabicFontSize);

    // Draw right Arabic
    $draw($rightShaped, $arabicFontSize, $rightX, $rightY);

    // Draw left
    $leftY = $baselineY($leftText, $latinFontSize);
    $draw($leftText, $latinFontSize, $padding, $leftY);

    // Center text: center it in remaining space BEFORE Arabic starts
    $cBox = imagettfbbox($latinFontSize, 0, $font, $centerText);
    $cW = abs($cBox[2] - $cBox[0]);
    $availRight = max($padding, $rightX - $padding);

    $centerX = max($padding, intdiv(($availRight - $cW), 2));
    $centerY = $baselineY($centerText, $latinFontSize);

    $draw($centerText, $latinFontSize, $centerX, $centerY);

    @mkdir(dirname($outputPath), 0755, true);
    imagepng($im, $outputPath);
    imagedestroy($im);

    return $outputPath;
}

// public static function createSingleRowImageForPrinter(
//     string $leftText,
//     string $centerText,
//     string $rightText,
//     string $outputPath,
//     int $paperDots = 576,
//     int $rowHeight = 38,
//     int $padding = 8,
//     int $latinFontSize = 20,
//     int $arabicFontSize = 16,
//     bool $bold = true
// ): string {
//     $fontPath = public_path('fonts/Amiri-Regular.ttf');
//     if (!file_exists($fontPath)) {
//         throw new \Exception("Font missing: " . $fontPath);
//     }

//     // Detect Arabic (any Arabic block chars)
//     $hasArabic = function(string $s): bool {
//         return (bool) preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}]/u', $s);
//     };

//     $Arabic = new Arabic('Glyphs');

//     // Shape if Arabic, otherwise keep as-is
//     $shapeIfArabic = function(string $s) use ($Arabic, $hasArabic): string {
//         return $hasArabic($s) ? $Arabic->utf8Glyphs($s) : $s;
//     };

//     $rightDraw  = $shapeIfArabic($rightText);
//     $centerDraw = $shapeIfArabic($centerText);

//     $rightSize  = $hasArabic($rightText)  ? $arabicFontSize : $latinFontSize;
//     $centerSize = $hasArabic($centerText) ? $arabicFontSize : $latinFontSize;

//     $w = $paperDots;
//     $h = $rowHeight;

//     $im = imagecreatetruecolor($w, $h);
//     $white = imagecolorallocate($im, 255, 255, 255);
//     $black = imagecolorallocate($im, 0, 0, 0);
//     imagefilledrectangle($im, 0, 0, $w, $h, $white);

//     $draw = function(string $txt, int $size, int $x, int $baselineY) use ($im, $fontPath, $black, $bold) {
//         imagettftext($im, $size, 0, $x, $baselineY, $black, $fontPath, $txt);
//         if ($bold) {
//             imagettftext($im, $size, 0, $x + 1, $baselineY, $black, $fontPath, $txt);
//         }
//     };

//     $baselineY = function(string $txt, int $size) use ($fontPath, $h): int {
//         $box = imagettfbbox($size, 0, $fontPath, $txt);
//         $textH = abs($box[7] - $box[1]);
//         return intdiv($h + $textH, 2) - 2;
//     };

//     // ---- RIGHT: measure & draw on far right ----
//     $rightBox = imagettfbbox($rightSize, 0, $fontPath, $rightDraw);
//     $rightW = abs($rightBox[2] - $rightBox[0]);

//     $rightX = max($padding, $w - $rightW - $padding);
//     $rightY = $baselineY($rightDraw, $rightSize);
//     $draw($rightDraw, $rightSize, $rightX, $rightY);

//     // ---- LEFT: draw at far left ----
//     $leftY = $baselineY($leftText, $latinFontSize);
//     $draw($leftText, $latinFontSize, $padding, $leftY);

//     // ---- CENTER: center inside the space BEFORE right starts ----
//     $centerBox = imagettfbbox($centerSize, 0, $fontPath, $centerDraw);
//     $centerW = abs($centerBox[2] - $centerBox[0]);

//     $availRight = max($padding, $rightX - $padding); // available width for center region
//     $centerX = max($padding, intdiv(($availRight - $centerW), 2));
//     $centerY = $baselineY($centerDraw, $centerSize);
//     $draw($centerDraw, $centerSize, $centerX, $centerY);

//     if (!is_dir(dirname($outputPath))) {
//         mkdir(dirname($outputPath), 0755, true);
//     }
//     imagepng($im, $outputPath);
//     imagedestroy($im);

//     return $outputPath;
// }

public static function createSingleRowImageForPrinter(
    string $leftText,
    string $centerText,
    string $rightText,
    string $outputPath,
    int $paperDots = 576,
    int $rowHeight = 38,
    int $padding = 8,
    int $latinFontSize = 22,
    int $arabicFontSize = 18,
    bool $bold = true,
    int $gapDots = 8 // minimum gap between blocks
): string {
    $fontPath = public_path('fonts/Amiri-Regular.ttf');
    if (!file_exists($fontPath)) {
        throw new \Exception("Font missing: " . $fontPath);
    }

    $hasArabic = function(string $s): bool {
        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}]/u', $s);
    };

    $Arabic = new Arabic('Glyphs');

    $shapeIfArabic = function(string $s) use ($Arabic, $hasArabic): string {
        return $hasArabic($s) ? $Arabic->utf8Glyphs($s) : $s;
    };

    // Shape all 3
    $leftDraw   = $shapeIfArabic($leftText);
    $centerDraw = $shapeIfArabic($centerText);
    $rightDraw  = $shapeIfArabic($rightText);

    // Font size per segment
    $leftSize   = $hasArabic($leftText)   ? $arabicFontSize : $latinFontSize;
    $centerSize = $hasArabic($centerText) ? $arabicFontSize : $latinFontSize;
    $rightSize  = $hasArabic($rightText)  ? $arabicFontSize : $latinFontSize;

    $w = $paperDots;
    $h = $rowHeight;

    $im = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($im, 255, 255, 255);
    $black = imagecolorallocate($im, 0, 0, 0);
    imagefilledrectangle($im, 0, 0, $w, $h, $white);

    $draw = function(string $txt, int $size, int $x, int $baselineY) use ($im, $fontPath, $black, $bold) {
        imagettftext($im, $size, 0, $x, $baselineY, $black, $fontPath, $txt);
        if ($bold) {
            imagettftext($im, $size, 0, $x + 1, $baselineY, $black, $fontPath, $txt);
        }
    };

    $baselineY = function(string $txt, int $size) use ($fontPath, $h): int {
        $box = imagettfbbox($size, 0, $fontPath, $txt);
        $textH = abs($box[7] - $box[1]);
        return intdiv($h + $textH, 2) - 2;
    };

    $measureW = function(string $txt, int $size) use ($fontPath): int {
        if ($txt === '') return 0;
        $box = imagettfbbox($size, 0, $fontPath, $txt);
        return abs($box[2] - $box[0]);
    };

    // ---- Measure widths ----
    $leftW  = $measureW($leftDraw,  $leftSize);
    $rightW = $measureW($rightDraw, $rightSize);
    $centerW= $measureW($centerDraw,$centerSize);

    // ---- Compute X positions ----
    $leftX = $padding;

    $rightX = max($padding, $w - $rightW - $padding);

    // Available region for center is between end of left block and start of right block
    $centerRegionLeft  = min($w, $leftX + $leftW + $gapDots);
    $centerRegionRight = max($centerRegionLeft, $rightX - $gapDots);

    // Center within that region
    $centerX = max(
        $centerRegionLeft,
        intdiv(($centerRegionLeft + $centerRegionRight - $centerW), 2)
    );

    // Clamp center so it doesn't cross region
    if ($centerX + $centerW > $centerRegionRight) {
        $centerX = max($centerRegionLeft, $centerRegionRight - $centerW);
    }

    // ---- Draw right, left, center ----
    if ($rightDraw !== '') {
        $draw($rightDraw, $rightSize, $rightX, $baselineY($rightDraw, $rightSize));
    }

    if ($leftDraw !== '') {
        $draw($leftDraw, $leftSize, $leftX, $baselineY($leftDraw, $leftSize));
    }

    if ($centerDraw !== '') {
        $draw($centerDraw, $centerSize, $centerX, $baselineY($centerDraw, $centerSize));
    }

    if (!is_dir(dirname($outputPath))) {
        mkdir(dirname($outputPath), 0755, true);
    }
    imagepng($im, $outputPath);
    imagedestroy($im);

    return $outputPath;
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
