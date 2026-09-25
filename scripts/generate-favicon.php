<?php
/**
 * Generate favicon.ico + PNG icons from brand mark (run once via CLI or browser).
 * Usage: C:\xampp\php\php.exe scripts/generate-favicon.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$assets = $root . '/public/assets/images';
if (!is_dir($assets)) {
    mkdir($assets, 0755, true);
}

function arowin_draw_icon(int $size): GdImage
{
    $im = imagecreatetruecolor($size, $size);
    imagesavealpha($im, true);
    $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefill($im, 0, 0, $transparent);

    $bg = imagecolorallocate($im, 10, 10, 10);
    $orange = imagecolorallocate($im, 255, 140, 26);
    $black = imagecolorallocate($im, 0, 0, 0);

    $pad = (int) max(1, $size * 0.06);
    imagefilledrectangle($im, $pad, $pad, $size - $pad - 1, $size - $pad - 1, $bg);

    // Outer diamond
    $cx = (int) ($size / 2);
    $cy = (int) ($size / 2);
    $r  = (int) ($size * 0.38);
    $outer = [
        $cx, $cy - $r,
        $cx + $r, $cy,
        $cx, $cy + $r,
        $cx - $r, $cy,
    ];
    imagefilledpolygon($im, $outer, $orange);

    // Inner diamond cutout
    $r2 = (int) ($size * 0.18);
    $inner = [
        $cx, $cy - $r2,
        $cx + $r2, $cy,
        $cx, $cy + $r2,
        $cx - $r2, $cy,
    ];
    imagefilledpolygon($im, $inner, $black);

    return $im;
}

function arowin_write_ico(string $path, array $sizes): void
{
    $images = [];
    foreach ($sizes as $size) {
        $im = arowin_draw_icon($size);
        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        imagedestroy($im);
        $images[] = ['size' => $size, 'data' => $png];
    }

    $count = count($images);
    $offset = 6 + ($count * 16);
    $dir = '';
    $body = '';

    foreach ($images as $img) {
        $size = $img['size'];
        $data = $img['data'];
        $len  = strlen($data);
        $w = $size >= 256 ? 0 : $size;
        $h = $size >= 256 ? 0 : $size;
        $dir .= pack('CCCCvvVV', $w, $h, 0, 0, 1, 32, $len, $offset);
        $body .= $data;
        $offset += $len;
    }

    $header = pack('vvv', 0, 1, $count);
    file_put_contents($path, $header . $dir . $body);
}

// SVG (crisp at any size — primary modern favicon)
$svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" role="img" aria-label="Arowin">
  <rect width="32" height="32" rx="8" fill="#0a0a0a"/>
  <path d="M16 6L26 16L16 26L6 16Z" fill="#ff8c1a"/>
  <path d="M16 11L21 16L16 21L11 16Z" fill="#000"/>
</svg>
SVG;
file_put_contents($assets . '/favicon.svg', $svg);

// PNG sizes
foreach ([16, 32, 48, 180, 192, 512] as $size) {
    $im = arowin_draw_icon($size);
    $name = $size === 180 ? 'apple-touch-icon.png' : "favicon-{$size}x{$size}.png";
    imagepng($im, $assets . '/' . $name, 6);
    imagedestroy($im);
}

// Root favicon.ico (16 + 32 + 48)
arowin_write_ico($root . '/favicon.ico', [16, 32, 48]);
copy($root . '/favicon.ico', $assets . '/favicon.ico');

// Simple OG placeholder 1200x630
$og = imagecreatetruecolor(1200, 630);
$bg = imagecolorallocate($og, 0, 0, 0);
$orange = imagecolorallocate($og, 255, 140, 26);
$white = imagecolorallocate($og, 255, 255, 255);
imagefill($og, 0, 0, $bg);
$cx = 600; $cy = 280; $r = 90;
imagefilledpolygon($og, [$cx, $cy - $r, $cx + $r, $cy, $cx, $cy + $r, $cx - $r, $cy], $orange);
imagefilledpolygon($og, [$cx, $cy - 40, $cx + 40, $cy, $cx, $cy + 40, $cx - 40, $cy], $bg);
imagestring($og, 5, 480, 420, 'AROWIN TRADING PORTAL', $white);
imagepng($og, $assets . '/og-arowin.png', 6);
imagedestroy($og);

echo "Favicons generated in /assets and /favicon.ico\n";
