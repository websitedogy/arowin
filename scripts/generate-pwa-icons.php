<?php
declare(strict_types=1);

$dir = dirname(__DIR__) . '/public/assets/images';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

function arowin_make_pwa_icon(int $size, string $path): void
{
    $im = imagecreatetruecolor($size, $size);
    imagesavealpha($im, true);
    $bg = imagecolorallocate($im, 10, 10, 10);
    $orange = imagecolorallocate($im, 255, 140, 26);
    $black = imagecolorallocate($im, 0, 0, 0);
    imagefilledrectangle($im, 0, 0, $size - 1, $size - 1, $bg);

    $cx = $size / 2;
    $cy = $size / 2;
    $r = (int) ($size * 0.34);
    imagefilledpolygon($im, [$cx, $cy - $r, $cx + $r, $cy, $cx, $cy + $r, $cx - $r, $cy], $orange);
    $r2 = (int) ($size * 0.16);
    imagefilledpolygon($im, [$cx, $cy - $r2, $cx + $r2, $cy, $cx, $cy + $r2, $cx - $r2, $cy], $black);

    imagepng($im, $path);
    imagedestroy($im);
}

arowin_make_pwa_icon(192, $dir . '/favicon-192x192.png');
arowin_make_pwa_icon(512, $dir . '/favicon-512x512.png');
arowin_make_pwa_icon(180, $dir . '/apple-touch-icon.png');

echo "icons-ok\n";
