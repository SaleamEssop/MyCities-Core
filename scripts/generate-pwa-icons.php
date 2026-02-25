<?php
/**
 * Generates PWA icons for MyCities using PHP GD.
 * Run during Docker build: php scripts/generate-pwa-icons.php
 * Primary colour: #009BA4 (teal)
 */

$iconsDir = __DIR__ . '/../public/icons';
if (!is_dir($iconsDir)) {
    mkdir($iconsDir, 0755, true);
}

$sizes = [192, 512];

foreach ($sizes as $size) {
    $img = imagecreatetruecolor($size, $size);

    // Teal background (#009BA4)
    $bg = imagecolorallocate($img, 0, 155, 164);
    imagefill($img, 0, 0, $bg);

    // Rounded-corner mask (approximate with filled ellipses in corners)
    $radius   = intval($size * 0.22);
    $white    = imagecolorallocate($img, 255, 255, 255);
    $darkTeal = imagecolorallocate($img, 0, 120, 128);

    // Draw a slightly darker inner circle as subtle depth
    $pad = intval($size * 0.06);
    imagefilledellipse($img, $size / 2, $size / 2, $size - $pad * 2, $size - $pad * 2, $darkTeal);

    // ── "MC" text centred ──────────────────────────────────────────
    // Use largest built-in bitmap font (font 5 = 9×15 px per char)
    $font       = 5;
    $charW      = imagefontwidth($font);
    $charH      = imagefontheight($font);
    $text       = 'MC';
    $scale      = intval($size / 96);   // scale repetitions for larger sizes
    $scale      = max(1, $scale);

    if ($scale === 1) {
        // Small icon: plain built-in font
        $tw = $charW * strlen($text);
        $th = $charH;
        $tx = intval(($size - $tw) / 2);
        $ty = intval(($size - $th) / 2);
        imagestring($img, $font, $tx, $ty, $text, $white);
    } else {
        // Larger icon: build character manually via imagestring on a temp canvas then resample
        $tmpSize = 96;
        $tmp     = imagecreatetruecolor($tmpSize, $tmpSize);
        $tmpBg   = imagecolorallocate($tmp, 0, 155, 164);
        imagefill($tmp, 0, 0, $tmpBg);
        $tmpWhite = imagecolorallocate($tmp, 255, 255, 255);
        $tw = $charW * strlen($text);
        $th = $charH;
        $tx = intval(($tmpSize - $tw) / 2);
        $ty = intval(($tmpSize - $th) / 2);
        imagestring($tmp, $font, $tx, $ty, $text, $tmpWhite);

        // Copy the text region scaled up into the main image
        $srcX = $tx - 2;
        $srcY = $ty - 2;
        $srcW = $tw + 4;
        $srcH = $th + 4;
        $dstW = intval($srcW * $scale * 1.8);
        $dstH = intval($srcH * $scale * 1.8);
        $dstX = intval(($size - $dstW) / 2);
        $dstY = intval(($size - $dstH) / 2);
        imagecopyresampled($img, $tmp, $dstX, $dstY, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($tmp);
    }

    $outPath = $iconsDir . "/icon-{$size}.png";
    imagepng($img, $outPath);
    imagedestroy($img);
    echo "Generated: icon-{$size}.png\n";
}

echo "PWA icons ready.\n";
