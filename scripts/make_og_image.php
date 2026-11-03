<?php
/**
 * Builds the 1200x630 social sharing card at assets/images/og-default.jpg.
 *
 *   E:/xampp/php/php.exe -d extension=gd scripts/make_og_image.php
 *
 * Re-run it after changing the restaurant name, address or phone in admin
 * settings, or after replacing the logo. Text is English-only on purpose:
 * GD cannot shape Bengali conjuncts, so Bangla would render as broken glyphs.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require dirname(__DIR__) . '/includes/bootstrap.php';

$out = DH_ROOT . '/assets/images/og-default.jpg';
$W = 1200;
$H = 630;
$photoSplit = 680;

$fonts = [
    'display' => 'C:/Windows/Fonts/cambriab.ttf',
    'bold' => 'C:/Windows/Fonts/calibrib.ttf',
    'body' => 'C:/Windows/Fonts/calibri.ttf',
];
foreach ($fonts as $role => $path) {
    if (!is_file($path)) {
        exit("Missing font for role '$role': $path\n");
    }
}

$logoPath = is_file(DH_ROOT . '/assets/images/logo.webp')
    ? DH_ROOT . '/assets/images/logo.webp'
    : null;
$photoPath = null;
foreach (['uploads/menu/seek-kabab.webp', 'assets/images/menu/seek-kabab.webp'] as $candidate) {
    if (is_file(DH_ROOT . '/' . $candidate)) {
        $photoPath = DH_ROOT . '/' . $candidate;
        break;
    }
}

/* ------------------------------------------------------------------ canvas */

$im = imagecreatetruecolor($W, $H);
$hex = static function (GdImage $im, string $h): int {
    $h = ltrim($h, '#');
    return imagecolorallocate($im, (int) hexdec(substr($h, 0, 2)), (int) hexdec(substr($h, 2, 2)), (int) hexdec(substr($h, 4, 2)));
};

$charcoalTop = $hex($im, '#2e2823');
$charcoalBottom = $hex($im, '#211d1a');
$ivory = $hex($im, '#fffdf7');
$onDark = $hex($im, '#f6efe3');
$onDarkMuted = $hex($im, '#c4b6a4');
$peach = $hex($im, '#e0a276');
$orange = $hex($im, '#c25a25');
$gold = $hex($im, '#c9a867');

// Deep charcoal plate, darker towards the bottom.
for ($y = 0; $y < $H; $y++) {
    $t = $y / ($H - 1);
    $r = (int) round(0x2e + (0x21 - 0x2e) * $t);
    $g = (int) round(0x28 + (0x1d - 0x28) * $t);
    $b = (int) round(0x23 + (0x1a - 0x23) * $t);
    imageline($im, 0, $y, $W, $y, imagecolorallocate($im, $r, $g, $b));
}

/* --------------------------------------------------------------------- photo */

if ($photoPath !== null) {
    $photo = imagecreatefromwebp($photoPath);
    $pw = imagesx($photo);
    $ph = imagesy($photo);
    $targetW = $W - $photoSplit;
    $scale = $H / $ph;
    $srcW = min($pw, (int) round($targetW / $scale));
    $srcX = intdiv($pw - $srcW, 2);
    imagecopyresampled($im, $photo, $photoSplit, 0, $srcX, 0, $targetW, $H, $srcW, $ph);
}

// Champagne gold rule between plate and photo.
imagealphablending($im, true);
imagefilledrectangle($im, $photoSplit - 3, 0, $photoSplit, $H, $gold);

/* ----------------------------------------------------------------- logo chip */

function roundedRect(GdImage $im, int $x, int $y, int $w, int $h, int $r, int $color): void
{
    imagefilledrectangle($im, $x + $r, $y, $x + $w - $r - 1, $y + $h - 1, $color);
    imagefilledrectangle($im, $x, $y + $r, $x + $r - 1, $y + $h - $r - 1, $color);
    imagefilledrectangle($im, $x + $w - $r, $y + $r, $x + $w - 1, $y + $h - $r - 1, $color);
    foreach ([[$x + $r, $y + $r], [$x + $w - $r - 1, $y + $r], [$x + $r, $y + $h - $r - 1], [$x + $w - $r - 1, $y + $h - $r - 1]] as [$cx, $cy]) {
        imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $color);
    }
}

if ($logoPath !== null) {
    $chip = 116;
    $chipX = 64;
    $chipY = 56;
    roundedRect($im, $chipX - 2, $chipY - 2, $chip + 4, $chip + 4, 22, $gold);
    roundedRect($im, $chipX, $chipY, $chip, $chip, 20, $ivory);

    $logo = imagecreatefromwebp($logoPath);
    $mark = 92;
    $lw = imagesx($logo);
    $lh = imagesy($logo);
    $dw = $mark;
    $dh = (int) round($mark * $lh / $lw);
    if ($dh > $mark) {
        $dh = $mark;
        $dw = (int) round($mark * $lw / $lh);
    }
    imagecopyresampled(
        $im,
        $logo,
        $chipX + intdiv($chip - $dw, 2),
        $chipY + intdiv($chip - $dh, 2),
        0,
        0,
        $dw,
        $dh,
        $lw,
        $lh
    );
}

/* ---------------------------------------------------------------------- text */

$name = restaurant('name', 'en');
if ($name === '') {
    $name = restaurant_name();
}
$subtitle = restaurant('tagline', 'en');
if ($subtitle === '') {
    $subtitle = 'Charcoal-grilled kabab, delivered hot';
}
$address = restaurant('address', 'en');
$phone = trim((string) setting('phone', ''));

function textWidth(string $font, int $px, string $s): int
{
    $bb = imagettfbbox($px, 0, $font, $s);
    return ($bb[2] ?? 0) - ($bb[0] ?? 0);
}

function wrapText(string $font, int $px, string $s, int $maxW, int $maxLines): array
{
    $words = preg_split('/\s+/', $s) ?: [];
    $lines = [];
    $current = '';
    foreach ($words as $word) {
        $try = $current === '' ? $word : $current . ' ' . $word;
        if (textWidth($font, $px, $try) > $maxW && $current !== '') {
            $lines[] = $current;
            $current = $word;
            if (count($lines) === $maxLines) {
                return $lines;
            }
        } else {
            $current = $try;
        }
    }
    if ($current !== '' && count($lines) < $maxLines) {
        $lines[] = $current;
    }
    return $lines;
}

$maxTextW = $photoSplit - 64 - 40;
$y = 286;
imagettftext($im, 62, 0, 64, $y, $onDark, $fonts['display'], $name);

$y += 48;
imagettftext($im, 27, 0, 64, $y, $peach, $fonts['bold'], $subtitle);

$y += 26;
imagefilledrectangle($im, 64, $y, 196, $y + 5, $orange);

$y += 58;
foreach (wrapText($fonts['body'], 26, $address, $maxTextW, 2) as $line) {
    imagettftext($im, 26, 0, 64, $y, $onDarkMuted, $fonts['body'], $line);
    $y += 34;
}

if ($phone !== '') {
    $y += 6;
    imagettftext($im, 28, 0, 64, $y, $onDark, $fonts['bold'], $phone);
}

imagejpeg($im, $out, 88);
printf("%s  %dx%d  %d bytes\n", $out, imagesx($im), imagesy($im), filesize($out));
