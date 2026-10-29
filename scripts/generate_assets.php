<?php
/**
 * Generates the local demo artwork (menu illustrations + PWA icons).
 * Run once: php scripts/generate_assets.php
 *
 * The artwork is original flat illustration, not photography, so the demo
 * never misrepresents a real restaurant's dishes.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$root = dirname(__DIR__);
$menuDir = $root . '/assets/images/menu';
$iconDir = $root . '/assets/images/icons';

foreach ([$menuDir, $iconDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

/* ---------------------------------------------------------------- helpers */

function svg_open(string $bgFrom, string $bgTo, int $w = 800, int $h = 600): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" role="img">'
        . '<defs>'
        . '<linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">'
        . '<stop offset="0" stop-color="' . $bgFrom . '"/><stop offset="1" stop-color="' . $bgTo . '"/>'
        . '</linearGradient>'
        . '<filter id="soft" x="-40%" y="-40%" width="180%" height="180%">'
        . '<feGaussianBlur stdDeviation="16"/></filter>'
        . '<filter id="blur6" x="-30%" y="-30%" width="160%" height="160%">'
        . '<feGaussianBlur stdDeviation="6"/></filter>'
        . '</defs>'
        . '<rect width="' . $w . '" height="' . $h . '" fill="url(#bg)"/>';
}

function svg_close(): string
{
    return '</svg>';
}

function blob(float $cx, float $cy, float $r, string $color, float $opacity = 0.35): string
{
    return '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="' . $color . '" opacity="' . $opacity . '" filter="url(#soft)"/>';
}

function shadow(float $cx, float $cy, float $rx, float $ry): string
{
    return '<ellipse cx="' . $cx . '" cy="' . $cy . '" rx="' . $rx . '" ry="' . $ry . '" fill="#5b2b1f" opacity="0.14" filter="url(#soft)"/>';
}

function plate(float $cx, float $cy, float $rx, float $ry, string $fill = '#FFFDF8', string $inner = '#FFF6E9'): string
{
    return '<ellipse cx="' . $cx . '" cy="' . $cy . '" rx="' . $rx . '" ry="' . $ry . '" fill="' . $fill . '"/>'
        . '<ellipse cx="' . $cx . '" cy="' . $cy . '" rx="' . ($rx * 0.86) . '" ry="' . ($ry * 0.84) . '" fill="' . $inner . '"/>'
        . '<ellipse cx="' . $cx . '" cy="' . $cy . '" rx="' . ($rx * 0.86) . '" ry="' . ($ry * 0.84) . '" fill="none" stroke="#EBD8C3" stroke-width="2" opacity="0.7"/>';
}

function bowl(float $cx, float $cy, float $rx, float $ry, string $rim, string $inner): string
{
    return '<ellipse cx="' . $cx . '" cy="' . $cy . '" rx="' . $rx . '" ry="' . $ry . '" fill="' . $rim . '"/>'
        . '<ellipse cx="' . $cx . '" cy="' . $cy . '" rx="' . ($rx * 0.88) . '" ry="' . ($ry * 0.84) . '" fill="' . $inner . '"/>';
}

function grain_texture(float $cx, float $cy, float $rx, float $ry, string $color, int $count, int $seed, float $size = 7): string
{
    mt_srand($seed);
    $out = '';
    for ($i = 0; $i < $count; $i++) {
        $angle = mt_rand(0, 628) / 100;
        $radius = sqrt(mt_rand(0, 1000) / 1000);
        $x = $cx + cos($angle) * $rx * $radius * 0.92;
        $y = $cy + sin($angle) * $ry * $radius * 0.92;
        $rotation = mt_rand(-70, 70);
        $len = $size * (0.6 + (mt_rand(0, 60) / 100));
        $out .= '<ellipse cx="' . round($x, 1) . '" cy="' . round($y, 1) . '" rx="' . round($len, 1) . '" ry="' . round($len * 0.42, 1)
            . '" fill="' . $color . '" opacity="' . (0.35 + mt_rand(0, 40) / 100) . '" transform="rotate(' . $rotation . ' ' . round($x, 1) . ' ' . round($y, 1) . ')"/>';
    }

    return $out;
}

function rice(float $cx, float $cy, float $rx, float $ry, string $base = '#F8EACB', string $grain = '#EBD3A2', int $seed = 3): string
{
    return '<ellipse cx="' . $cx . '" cy="' . $cy . '" rx="' . $rx . '" ry="' . $ry . '" fill="' . $base . '"/>'
        . '<ellipse cx="' . ($cx - $rx * 0.18) . '" cy="' . ($cy - $ry * 0.2) . '" rx="' . ($rx * 0.62) . '" ry="' . ($ry * 0.55) . '" fill="#FBF2DC" opacity="0.75"/>'
        . grain_texture($cx, $cy, $rx, $ry, $grain, 90, $seed);
}

function meat(float $cx, float $cy, float $w, float $h, float $rot, string $color = '#8A4B2A'): string
{
    $x = $cx - $w / 2;
    $y = $cy - $h / 2;

    return '<g transform="rotate(' . $rot . ' ' . $cx . ' ' . $cy . ')">'
        . '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" rx="' . ($h * 0.34) . '" fill="' . $color . '"/>'
        . '<rect x="' . ($x + $w * 0.12) . '" y="' . ($y + $h * 0.12) . '" width="' . ($w * 0.62) . '" height="' . ($h * 0.3) . '" rx="' . ($h * 0.16) . '" fill="#FFFFFF" opacity="0.16"/>'
        . '</g>';
}

function leaf(float $x, float $y, float $scale, float $rot, string $color = '#5F8F45'): string
{
    return '<g transform="translate(' . $x . ' ' . $y . ') rotate(' . $rot . ') scale(' . $scale . ')">'
        . '<path d="M0 0 C 10 -16, 34 -18, 44 -2 C 32 12, 10 12, 0 0 Z" fill="' . $color . '"/>'
        . '<path d="M4 0 C 16 -4, 30 -6, 40 -3" stroke="#FFFFFF" stroke-width="1.6" opacity="0.5" fill="none"/>'
        . '</g>';
}

function egg_half(float $x, float $y, float $r): string
{
    return '<circle cx="' . $x . '" cy="' . $y . '" r="' . $r . '" fill="#FFFDF6"/>'
        . '<circle cx="' . $x . '" cy="' . $y . '" r="' . ($r * 0.46) . '" fill="#F2B441"/>'
        . '<circle cx="' . ($x - $r * 0.16) . '" cy="' . ($y - $r * 0.18) . '" r="' . ($r * 0.16) . '" fill="#FFFFFF" opacity="0.5"/>';
}

function lime_wedge(float $x, float $y, float $r, float $rot): string
{
    return '<g transform="rotate(' . $rot . ' ' . $x . ' ' . $y . ')">'
        . '<path d="M' . $x . ' ' . $y . ' L ' . ($x + $r) . ' ' . ($y - $r * 0.55) . ' A ' . $r . ' ' . $r . ' 0 0 1 ' . ($x + $r) . ' ' . ($y + $r * 0.55) . ' Z" fill="#B7D96A"/>'
        . '<path d="M' . $x . ' ' . $y . ' L ' . ($x + $r * 0.82) . ' ' . ($y - $r * 0.3) . ' A ' . ($r * 0.86) . ' ' . ($r * 0.86) . ' 0 0 1 ' . ($x + $r * 0.82) . ' ' . ($y + $r * 0.3) . ' Z" fill="#EAF6C0"/>'
        . '</g>';
}

function onion_rings(float $cx, float $cy): string
{
    return '';
}

function glass(float $cx, float $cy, float $w, float $h, string $liquid, string $top = '#FFFFFF', bool $straw = false): string
{
    $topY = $cy - $h / 2;
    $bottomY = $cy + $h / 2;
    $topHalf = $w / 2;
    $bottomHalf = $w / 2 * 0.78;
    $out = '';
    if ($straw) {
        $out .= '<rect x="' . ($cx + $w * 0.12) . '" y="' . ($topY - 78) . '" width="10" height="' . ($h + 40) . '" rx="5" fill="#F2A3B4"/>';
    }
    $out .= '<path d="M' . ($cx - $topHalf) . ' ' . $topY . ' L' . ($cx + $topHalf) . ' ' . $topY
        . ' L' . ($cx + $bottomHalf) . ' ' . ($bottomY - 14) . ' Q' . $cx . ' ' . ($bottomY + 18) . ' ' . ($cx - $bottomHalf) . ' ' . ($bottomY - 14) . ' Z"'
        . ' fill="#FFFFFF" opacity="0.55"/>';
    $out .= '<path d="M' . ($cx - $topHalf + 8) . ' ' . ($topY + 12) . ' L' . ($cx + $topHalf - 8) . ' ' . ($topY + 12)
        . ' L' . ($cx + $bottomHalf - 12) . ' ' . ($bottomY - 22) . ' Q' . $cx . ' ' . ($bottomY + 4) . ' ' . ($cx - $bottomHalf + 12) . ' ' . ($bottomY - 22) . ' Z"'
        . ' fill="' . $liquid . '"/>'
        . '<ellipse cx="' . $cx . '" cy="' . ($topY + 12) . '" rx="' . ($topHalf - 8) . '" ry="9" fill="' . $top . '"/>';
    $out .= '<path d="M' . ($cx - $topHalf) . ' ' . $topY . ' L' . ($cx + $topHalf) . ' ' . $topY
        . ' L' . ($cx + $bottomHalf) . ' ' . ($bottomY - 14) . ' Q' . $cx . ' ' . ($bottomY + 18) . ' ' . ($cx - $bottomHalf) . ' ' . ($bottomY - 14) . ' Z"'
        . ' fill="none" stroke="#FFFFFF" stroke-width="6" opacity="0.85"/>';

    return $out;
}

function burger(float $cx, float $cy): string
{
    $out = '';
    // bottom bun
    $out .= '<path d="M' . ($cx - 120) . ' ' . ($cy + 40) . ' Q' . $cx . ' ' . ($cy + 100) . ' ' . ($cx + 120) . ' ' . ($cy + 40) . ' L' . ($cx + 120) . ' ' . ($cy + 26) . ' L' . ($cx - 120) . ' ' . ($cy + 26) . ' Z" fill="#E4A85F"/>';
    // cheese
    $out .= '<path d="M' . ($cx - 122) . ' ' . ($cy + 8) . ' L' . ($cx + 122) . ' ' . ($cy + 8) . ' L' . ($cx + 96) . ' ' . ($cy + 40) . ' L' . ($cx + 60) . ' ' . ($cy + 26) . ' L' . ($cx - 60) . ' ' . ($cy + 40) . ' L' . ($cx - 96) . ' ' . ($cy + 26) . ' Z" fill="#F6C355"/>';
    // lettuce
    $out .= '<path d="M' . ($cx - 128) . ' ' . ($cy - 6) . ' Q' . ($cx - 96) . ' ' . ($cy + 26) . ' ' . ($cx - 60) . ' ' . ($cy + 2)
        . ' Q' . ($cx - 26) . ' ' . ($cy + 30) . ' ' . ($cx + 10) . ' ' . ($cy + 2)
        . ' Q' . ($cx + 46) . ' ' . ($cy + 30) . ' ' . ($cx + 82) . ' ' . ($cy + 2)
        . ' Q' . ($cx + 110) . ' ' . ($cy + 22) . ' ' . ($cx + 128) . ' ' . ($cy - 6) . ' Z" fill="#7FB15B"/>';
    // patty
    $out .= '<rect x="' . ($cx - 116) . '" y="' . ($cy - 46) . '" width="232" height="44" rx="22" fill="#8A4B2A"/>'
        . '<rect x="' . ($cx - 96) . '" y="' . ($cy - 38) . '" width="150" height="12" rx="6" fill="#FFFFFF" opacity="0.12"/>';
    // top bun
    $out .= '<path d="M' . ($cx - 126) . ' ' . ($cy - 50) . ' Q' . $cx . ' ' . ($cy - 166) . ' ' . ($cx + 126) . ' ' . ($cy - 50) . ' Z" fill="#E8AE64"/>';
    // sesame
    $seedOffsets = [[-62, -96], [-16, -118], [30, -108], [72, -84], [-92, -70], [8, -78], [56, -62]];
    foreach ($seedOffsets as [$sx, $sy]) {
        $out .= '<ellipse cx="' . ($cx + $sx) . '" cy="' . ($cy + $sy) . '" rx="7" ry="4" fill="#FFF4D6" transform="rotate(-18 ' . ($cx + $sx) . ' ' . ($cy + $sy) . ')"/>';
    }

    return $out;
}

function fries_carton(float $cx, float $cy): string
{
    $out = '';
    $fries = [[-44, -78, -12], [-20, -96, 6], [6, -88, -6], [30, -74, 14], [46, -58, -16], [-60, -50, 22], [16, -52, 2], [-6, -64, -24]];
    foreach ($fries as [$dx, $dy, $rot]) {
        $out .= '<rect x="' . ($cx + $dx - 11) . '" y="' . ($cy + $dy - 52) . '" width="22" height="86" rx="9" fill="#F4C25C" transform="rotate(' . $rot . ' ' . ($cx + $dx) . ' ' . ($cy + $dy) . ')"/>';
        $out .= '<rect x="' . ($cx + $dx - 6) . '" y="' . ($cy + $dy - 46) . '" width="7" height="66" rx="4" fill="#FFFFFF" opacity="0.28" transform="rotate(' . $rot . ' ' . ($cx + $dx) . ' ' . ($cy + $dy) . ')"/>';
    }
    $out .= '<path d="M' . ($cx - 92) . ' ' . ($cy - 6) . ' L' . ($cx + 92) . ' ' . ($cy - 6) . ' L' . ($cx + 66) . ' ' . ($cy + 120) . ' L' . ($cx - 66) . ' ' . ($cy + 120) . ' Z" fill="#E2543F"/>';
    $out .= '<rect x="' . ($cx - 74) . '" y="' . ($cy + 34) . '" width="148" height="26" rx="13" fill="#FFF6E9" opacity="0.9"/>';

    return $out;
}

function sandwich(float $cx, float $cy): string
{
    $out = '';
    $out .= '<g transform="rotate(-8 ' . $cx . ' ' . $cy . ')">';
    $out .= '<path d="M' . ($cx - 130) . ' ' . ($cy + 46) . ' L' . ($cx + 20) . ' ' . ($cy + 46) . ' L' . ($cx - 20) . ' ' . ($cy - 12) . ' L' . ($cx - 130) . ' ' . ($cy - 12) . ' Z" fill="#E9B875"/>';
    $out .= '<path d="M' . ($cx - 124) . ' ' . ($cy + 38) . ' L' . ($cx + 6) . ' ' . ($cy + 38) . ' L' . ($cx - 24) . ' ' . ($cy - 4) . ' L' . ($cx - 124) . ' ' . ($cy - 4) . ' Z" fill="#F7EFD8"/>';
    $out .= '<path d="M' . ($cx - 122) . ' ' . ($cy - 8) . ' L' . ($cx - 8) . ' ' . ($cy - 8) . ' L' . ($cx - 18) . ' ' . ($cy - 30) . ' L' . ($cx - 122) . ' ' . ($cy - 30) . ' Z" fill="#7FB15B"/>';
    $out .= '<path d="M' . ($cx - 120) . ' ' . ($cy - 26) . ' L' . ($cx - 4) . ' ' . ($cy - 26) . ' L' . ($cx - 14) . ' ' . ($cy - 58) . ' L' . ($cx - 120) . ' ' . ($cy - 58) . ' Z" fill="#D98B5F"/>';
    $out .= '<path d="M' . ($cx - 132) . ' ' . ($cy - 56) . ' Q' . ($cx - 60) . ' ' . ($cy - 96) . ' ' . ($cx + 4) . ' ' . ($cy - 56) . ' Z" fill="#E9B875"/>';
    $out .= '</g>';

    return $out;
}

function pudding_bowl(float $cx, float $cy): string
{
    $out = bowl($cx, $cy + 40, 170, 120, '#C8703F', '#FFF9EC');
    $out .= '<ellipse cx="' . $cx . '" cy="' . ($cy + 26) . '" rx="132" ry="86" fill="#FFFCF3"/>';
    $out .= '<ellipse cx="' . $cx . '" cy="' . ($cy + 20) . '" rx="96" ry="60" fill="#FFFFFF" opacity="0.6"/>';
    $out .= '<circle cx="' . ($cx - 40) . '" cy="' . ($cy + 6) . '" r="8" fill="#8BB04F"/>';
    $out .= '<circle cx="' . ($cx + 18) . '" cy="' . ($cy + 34) . '" r="7" fill="#8BB04F"/>';
    $out .= '<circle cx="' . ($cx + 54) . '" cy="' . ($cy - 6) . '" r="6" fill="#C2513B"/>';
    $out .= '<circle cx="' . ($cx - 8) . '" cy="' . ($cy - 20) . '" r="6" fill="#C2513B"/>';
    $out .= '<circle cx="' . ($cx + 40) . '" cy="' . ($cy + 46) . '" r="5" fill="#8BB04F"/>';

    return $out;
}

function save_svg(string $path, string $svg): void
{
    file_put_contents($path, $svg);
    echo '  wrote ' . basename($path) . ' (' . strlen($svg) . " bytes)\n";
}

/* ------------------------------------------------------------ dish scenes */

$cream = function (string $name, callable $scene): void {
    global $menuDir;
    $svg = $scene();
    save_svg($menuDir . '/' . $name . '.svg', $svg);
};

// Chicken biryani
$cream('chicken-biryani', function (): string {
    $s = svg_open('#FFF6EC', '#FFE3CC');
    $s .= blob(140, 120, 130, '#FFD0B0', 0.6) . blob(670, 480, 150, '#FFD9BC', 0.7);
    $s .= shadow(400, 360, 270, 72);
    $s .= plate(400, 320, 258, 196);
    $s .= rice(370, 300, 180, 118, '#F9EBCB', '#EAD1A0', 11);
    $s .= rice(330, 268, 110, 66, '#FBF2DC', '#EBD8AE', 21);
    $s .= meat(300, 330, 96, 58, -14) . meat(455, 350, 88, 52, 12) . meat(390, 250, 78, 46, -6, '#7A3F22');
    $s .= '<ellipse cx="470" cy="300" rx="22" ry="14" fill="#C08B3E" opacity="0.85" transform="rotate(-20 470 300)"/>';
    $s .= egg_half(472, 372, 30);
    $s .= leaf(300, 402, 0.9, -10) . leaf(340, 420, 0.8, 25) . leaf(560, 396, 0.85, -30);
    $s .= lime_wedge(250, 400, 34, 200);
    $s .= '<circle cx="560" cy="272" r="22" fill="#E45C48" opacity="0.9"/>'
        . '<circle cx="592" cy="292" r="16" fill="#8FBF5A" opacity="0.9"/>';
    return $s . svg_close();
});

// Beef tehari
$cream('beef-tehari', function (): string {
    $s = svg_open('#FFF3E6', '#F6D6B4');
    $s .= blob(680, 130, 140, '#F7C9A2', 0.55) . blob(120, 500, 130, '#F9D9B8', 0.6);
    $s .= shadow(400, 360, 265, 70);
    $s .= plate(400, 320, 252, 192);
    $s .= rice(400, 312, 178, 116, '#EFD8A8', '#DDBE83', 31);
    $s .= meat(330, 296, 92, 60, 10, '#7E4426') . meat(452, 320, 86, 56, -12, '#8A4B2A') . meat(392, 244, 74, 48, 4, '#6F3B20');
    $s .= '<ellipse cx="452" cy="378" rx="44" ry="34" fill="#E8C27B"/>'
        . '<ellipse cx="446" cy="370" rx="30" ry="22" fill="#F3D79B" opacity="0.7"/>';
    $s .= '<ellipse cx="262" cy="342" rx="30" ry="18" fill="#C08B3E" opacity="0.8" transform="rotate(18 262 342)"/>';
    $s .= leaf(310, 410, 0.85, 8) . leaf(480, 240, 0.75, -22);
    return $s . svg_close();
});

// Kacchi biryani
$cream('kacchi-biryani', function (): string {
    $s = svg_open('#FFF8EC', '#FFE6CF');
    $s .= blob(680, 470, 140, '#FFD6B5', 0.65) . blob(120, 110, 120, '#FFE1C2', 0.7);
    $s .= shadow(400, 366, 268, 72);
    $s .= plate(400, 320, 256, 196);
    $s .= rice(400, 306, 182, 118, '#F7E7C2', '#E7CB93', 41);
    $s .= rice(360, 270, 104, 62, '#FAF1D9', '#EDD9AF', 43);
    $s .= meat(340, 336, 100, 62, -8, '#8A4B2A') . meat(462, 300, 84, 54, 16, '#7A3F22');
    $s .= '<ellipse cx="440" cy="382" rx="52" ry="38" fill="#E9C786"/>'
        . '<ellipse cx="434" cy="374" rx="36" ry="25" fill="#F5DDAC" opacity="0.7"/>';
    $s .= egg_half(300, 384, 26);
    $s .= leaf(520, 380, 0.85, -18) . leaf(318, 248, 0.7, 12);
    $s .= '<ellipse cx="524" cy="250" rx="18" ry="11" fill="#C08B3E" opacity="0.9" transform="rotate(-24 524 250)"/>';
    return $s . svg_close();
});

// Plain rice
$cream('plain-rice', function (): string {
    $s = svg_open('#FBF8F1', '#F0E7D6');
    $s .= blob(660, 140, 120, '#EADFC8', 0.6) . blob(140, 480, 130, '#EFE6D3', 0.7);
    $s .= shadow(400, 372, 232, 64);
    $s .= bowl(400, 330, 222, 162, '#F7F2E8', '#FFFFFF');
    $s .= rice(400, 318, 168, 108, '#FFFFFF', '#EDE6D5', 53);
    $s .= '<ellipse cx="356" cy="316" rx="60" ry="34" fill="#FFFFFF" opacity="0.9"/>';
    $s .= leaf(540, 404, 0.75, -22, '#6C9C4C') . leaf(268, 404, 0.7, 18, '#6C9C4C');
    return $s . svg_close();
});

// Chicken curry
$cream('chicken-curry', function (): string {
    $s = svg_open('#FFF4EA', '#FAD9BD');
    $s .= blob(150, 140, 135, '#F8C9A5', 0.6) . blob(660, 500, 140, '#F6C9A3', 0.65);
    $s .= shadow(400, 372, 248, 68);
    $s .= bowl(400, 326, 236, 172, '#B7663B', '#D98540');
    $s .= '<ellipse cx="392" cy="316" rx="196" ry="136" fill="#C96F33"/>';
    $s .= '<ellipse cx="382" cy="304" rx="142" ry="88" fill="#D9853F" opacity="0.85"/>';
    $s .= '<ellipse cx="430" cy="330" rx="70" ry="46" fill="#B85A28" opacity="0.55"/>';
    $s .= meat(348, 300, 96, 60, -24, '#9A5D31') . meat(470, 330, 76, 48, 18, '#A9683A');
    $s .= '<ellipse cx="370" cy="382" rx="44" ry="30" fill="#EBC583"/>'
        . '<ellipse cx="364" cy="374" rx="28" ry="18" fill="#F3D9A4" opacity="0.7"/>';
    $s .= '<circle cx="470" cy="266" r="7" fill="#F2B441" opacity="0.85"/>'
        . '<circle cx="500" cy="292" r="5" fill="#F2B441" opacity="0.7"/>'
        . '<circle cx="300" cy="352" r="6" fill="#F2B441" opacity="0.7"/>';
    $s .= leaf(320, 246, 0.75, -14, '#4F7A3A') . leaf(510, 388, 0.8, 22, '#4F7A3A');
    return $s . svg_close();
});

// Beef bhuna
$cream('beef-bhuna', function (): string {
    $s = svg_open('#FFF2E8', '#F0D2B9');
    $s .= blob(670, 150, 145, '#EFC8A8', 0.6) . blob(120, 480, 130, '#F3D3B6', 0.6);
    $s .= shadow(400, 372, 250, 68);
    $s .= bowl(400, 326, 238, 174, '#8E5133', '#6E3A22');
    $s .= '<ellipse cx="396" cy="318" rx="198" ry="138" fill="#7B4026"/>';
    $s .= meat(330, 306, 98, 64, -18, '#5F3018') . meat(452, 326, 92, 58, 14, '#6A361C') . meat(392, 250, 78, 50, 6, '#552A15');
    $s .= '<ellipse cx="404" cy="392" rx="60" ry="26" fill="#8E4A28" opacity="0.55"/>';
    $s .= '<circle cx="470" cy="268" r="6" fill="#E8A03A"/>' . '<circle cx="300" cy="356" r="5" fill="#E8A03A"/>'
        . '<circle cx="342" cy="252" r="6" fill="#E8A03A" opacity="0.8"/>';
    $s .= leaf(492, 254, 0.7, -26) . leaf(282, 300, 0.7, 16);
    return $s . svg_close();
});

// Chicken roast
$cream('chicken-roast', function (): string {
    $s = svg_open('#FFF7EC', '#FBE0C6');
    $s .= blob(150, 460, 135, '#F6D2B0', 0.6) . blob(670, 140, 130, '#FADFC2', 0.7);
    $s .= shadow(400, 366, 258, 70);
    $s .= plate(400, 320, 252, 190);
    $s .= '<ellipse cx="392" cy="330" rx="150" ry="96" fill="#B96B33"/>';
    $s .= '<ellipse cx="386" cy="322" rx="132" ry="82" fill="#D08544"/>';
    $s .= '<ellipse cx="360" cy="300" rx="70" ry="44" fill="#E09A55" opacity="0.8"/>';
    $s .= '<ellipse cx="468" cy="366" rx="46" ry="30" fill="#9E5627" opacity="0.7"/>';
    $s .= '<ellipse cx="424" cy="286" rx="34" ry="22" fill="#C97A3B" opacity="0.8"/>';
    $s .= '<ellipse cx="330" cy="392" rx="26" ry="16" fill="#F2D9A4" opacity="0.7"/>';
    $s .= '<circle cx="520" cy="300" r="30" fill="none" stroke="#EBD3A2" stroke-width="9" opacity="0.9"/>'
        . '<circle cx="520" cy="300" r="14" fill="none" stroke="#EBD3A2" stroke-width="7" opacity="0.9"/>';
    $s .= leaf(290, 268, 0.8, -18) . leaf(540, 378, 0.75, 24);
    $s .= lime_wedge(286, 380, 32, 190);
    return $s . svg_close();
});

// Chicken burger
$cream('chicken-burger', function (): string {
    $s = svg_open('#FFF6EE', '#FFDFCC');
    $s .= blob(660, 150, 130, '#FFCFB0', 0.6) . blob(150, 480, 130, '#FFD8BB', 0.6);
    $s .= shadow(400, 452, 220, 40);
    $s .= '<ellipse cx="400" cy="452" rx="240" ry="52" fill="#FFF9F0"/>';
    $s .= burger(392, 320);
    $s .= '<g opacity="0.92"><rect x="590" y="330" width="20" height="110" rx="9" fill="#F4C25C" transform="rotate(12 600 385)"/>'
        . '<rect x="622" y="318" width="20" height="112" rx="9" fill="#F4C25C" transform="rotate(-8 632 374)"/>'
        . '<rect x="650" y="338" width="20" height="100" rx="9" fill="#F4C25C" transform="rotate(18 660 388)"/></g>';
    $s .= leaf(210, 400, 0.9, 12) . leaf(196, 430, 0.8, -20);
    return $s . svg_close();
});

// French fries
$cream('french-fries', function (): string {
    $s = svg_open('#FFF9F2', '#FFE4D2');
    $s .= blob(670, 150, 130, '#FFD3B5', 0.6) . blob(130, 480, 120, '#FFDCC4', 0.65);
    $s .= shadow(400, 470, 200, 44);
    $s .= '<ellipse cx="400" cy="470" rx="215" ry="46" fill="#FFFBF5"/>';
    $s .= fries_carton(400, 300);
    $s .= '<ellipse cx="596" cy="428" rx="52" ry="26" fill="#FFF6E9"/>'
        . '<ellipse cx="596" cy="424" rx="38" ry="18" fill="#D6473B"/>';
    $s .= leaf(210, 420, 0.8, -12);
    return $s . svg_close();
});

// Chicken sandwich
$cream('chicken-sandwich', function (): string {
    $s = svg_open('#FFFAF2', '#FBE3CC');
    $s .= blob(660, 480, 130, '#F7D6B8', 0.6) . blob(140, 130, 120, '#FBE6CF', 0.7);
    $s .= shadow(400, 440, 220, 46);
    $s .= '<ellipse cx="400" cy="440" rx="240" ry="54" fill="#FFFBF4"/>';
    $s .= sandwich(392, 320);
    $s .= '<circle cx="596" cy="372" r="40" fill="#E45C48" opacity="0.9"/>'
        . '<circle cx="586" cy="360" r="10" fill="#F4A03F" opacity="0.8"/>';
    $s .= leaf(196, 404, 0.85, 18);
    return $s . svg_close();
});

// Vegetable fried rice
$cream('vegetable-fried-rice', function (): string {
    $s = svg_open('#FFF8F0', '#F1E4CF');
    $s .= blob(670, 140, 120, '#EFE0C6', 0.6) . blob(130, 490, 130, '#F4E7D2', 0.6);
    $s .= shadow(400, 366, 258, 70);
    $s .= plate(400, 320, 252, 190);
    $s .= rice(400, 312, 178, 114, '#F6E6C4', '#E4C88F', 61);
    $s .= '<circle cx="344" cy="292" r="10" fill="#7FB15B"/>' . '<circle cx="470" cy="316" r="9" fill="#7FB15B"/>'
        . '<circle cx="390" cy="256" r="8" fill="#7FB15B"/>' . '<circle cx="430" cy="368" r="9" fill="#7FB15B"/>'
        . '<rect x="300" y="340" width="26" height="14" rx="6" fill="#E98A3C" transform="rotate(-18 313 347)"/>'
        . '<rect x="480" y="272" width="24" height="13" rx="6" fill="#E98A3C" transform="rotate(14 492 278)"/>'
        . '<rect x="410" y="300" width="22" height="12" rx="5" fill="#7A4B2A" transform="rotate(-8 421 306)"/>'
        . '<rect x="360" cy="0" y="382" width="24" height="13" rx="6" fill="#7A4B2A" transform="rotate(10 372 388)"/>';
    $s .= leaf(540, 380, 0.85, -20) . leaf(276, 258, 0.75, 14);
    return $s . svg_close();
});

// Egg fried rice
$cream('egg-fried-rice', function (): string {
    $s = svg_open('#FFF9EE', '#F6E6C6');
    $s .= blob(150, 460, 130, '#F2DFBB', 0.6) . blob(670, 150, 130, '#F8EACD', 0.65);
    $s .= shadow(400, 366, 255, 70);
    $s .= plate(400, 320, 250, 190);
    $s .= rice(400, 314, 176, 112, '#F5E3BF', '#E3C68C', 71);
    $s .= '<path d="M310 320 q 40 -46 84 -8 q 36 30 72 -4 q -18 44 -62 40 q -50 -4 -94 -28 Z" fill="#F6C64F"/>'
        . '<path d="M356 292 q 34 -26 62 4 q -30 26 -62 -4 Z" fill="#FFF4D2"/>';
    $s .= '<circle cx="470" cy="360" r="8" fill="#7FB15B"/>' . '<circle cx="330" cy="262" r="7" fill="#7FB15B"/>';
    $s .= leaf(520, 388, 0.8, -16) . leaf(288, 390, 0.7, 20);
    return $s . svg_close();
});

// Borhani
$cream('borhani', function (): string {
    $s = svg_open('#F5F8EE', '#DFEAD2');
    $s .= blob(650, 150, 130, '#D8E8C6', 0.7) . blob(150, 480, 120, '#E4EFD6', 0.7);
    $s .= shadow(400, 470, 150, 36);
    $s .= glass(396, 330, 210, 300, '#F6FAEE', '#FDFEF9');
    $s .= '<ellipse cx="396" cy="196" rx="88" ry="22" fill="#FDFEF9"/>'
        . '<ellipse cx="396" cy="192" rx="70" ry="14" fill="#EAF3D6"/>';
    $s .= leaf(430, 196, 0.6, -30, '#4F7A3A') . leaf(360, 200, 0.5, 12, '#5F8F45');
    $s .= '<circle cx="376" cy="260" r="4" fill="#C8DCA9" opacity="0.9"/>' . '<circle cx="420" cy="300" r="3" fill="#C8DCA9" opacity="0.8"/>';
    $s .= '<ellipse cx="500" cy="324" rx="38" ry="26" fill="#B7D96A" transform="rotate(-18 500 324)"/>'
        . '<ellipse cx="500" cy="324" rx="24" ry="16" fill="#EAF6C0" transform="rotate(-18 500 324)"/>';
    return $s . svg_close();
});

// Lime juice
$cream('lime-juice', function (): string {
    $s = svg_open('#FBFCEF', '#E7F1C9');
    $s .= blob(660, 150, 130, '#DDEBB4', 0.7) . blob(140, 480, 125, '#E9F2CE', 0.7);
    $s .= shadow(400, 470, 150, 36);
    $s .= glass(396, 330, 206, 300, '#F4D65C', '#FBE68A', true);
    $s .= '<rect x="352" y="250" width="44" height="40" rx="10" fill="#FFFFFF" opacity="0.45" transform="rotate(-12 374 270)"/>'
        . '<rect x="410" y="300" width="38" height="34" rx="9" fill="#FFFFFF" opacity="0.35" transform="rotate(14 429 317)"/>';
    $s .= '<ellipse cx="500" cy="316" rx="40" ry="28" fill="#B7D96A" transform="rotate(-14 500 316)"/>'
        . '<ellipse cx="500" cy="316" rx="25" ry="17" fill="#EAF6C0" transform="rotate(-14 500 316)"/>';
    $s .= leaf(486, 388, 0.6, 22, '#4F7A3A');
    return $s . svg_close();
});

// Firni
$cream('firni', function (): string {
    $s = svg_open('#FFF7F1', '#F7DCC8');
    $s .= blob(660, 150, 125, '#F5D3BC', 0.7) . blob(140, 470, 125, '#F9E1CE', 0.7);
    $s .= shadow(400, 400, 220, 52);
    $s .= pudding_bowl(400, 250);
    $s .= '<ellipse cx="400" cy="176" rx="70" ry="18" fill="#FFFFFF" opacity="0.5"/>';
    return $s . svg_close();
});

/* ------------------------------------------------------------------- hero */

$hero = svg_open('#FFF7F0', '#FFDCC8', 1200, 900);
$hero .= blob(230, 200, 220, '#FFCFB4', 0.55) . blob(1000, 720, 240, '#FFD3B6', 0.6) . blob(980, 160, 150, '#FFE2CB', 0.7);
$hero .= shadow(520, 560, 380, 100);
$hero .= plate(520, 470, 380, 288);
$hero .= rice(500, 440, 270, 176, '#F9EBCB', '#EAD1A0', 101);
$hero .= rice(430, 400, 160, 96, '#FCF4E0', '#EDDAB2', 111);
$hero .= meat(430, 480, 150, 92, -12) . meat(646, 500, 140, 84, 14) . meat(540, 360, 120, 74, -6, '#7A3F22');
$hero .= egg_half(690, 396, 44);
$hero .= '<ellipse cx="600" cy="300" rx="34" ry="20" fill="#C08B3E" opacity="0.85" transform="rotate(-20 600 300)"/>';
$hero .= leaf(400, 590, 1.4, -12) . leaf(470, 618, 1.25, 22) . leaf(800, 590, 1.3, -32);
$hero .= lime_wedge(320, 592, 52, 200);
$hero .= '<circle cx="812" cy="356" r="34" fill="#E45C48" opacity="0.9"/>'
    . '<circle cx="856" cy="386" r="24" fill="#8FBF5A" opacity="0.9"/>';
$hero .= glass(950, 440, 170, 250, '#F6FAEE', '#FDFEF9');
$hero .= leaf(975, 322, 0.7, -28, '#4F7A3A');
$hero .= '<g opacity="0.95"><ellipse cx="250" cy="290" rx="118" ry="78" fill="#FFFDF7"/>'
    . '<ellipse cx="250" cy="288" rx="92" ry="58" fill="#E9F0D3"/></g>';
$hero .= leaf(206, 292, 1.0, 8) . leaf(262, 258, 0.9, -22);
$hero .= svg_close();

save_svg($root . '/assets/images/hero.svg', $hero);

/* ------------------------------------------------------------------ icons */

function make_icon(string $path, int $size, bool $rounded = true): void
{
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);
    imageantialias($img, true);

    // Gradient background (pink -> deep pink).
    for ($y = 0; $y < $size; $y++) {
        $t = $y / max(1, $size - 1);
        $r = (int) (255 - 30 * $t);
        $g = (int) (122 - 60 * $t);
        $b = (int) (154 - 40 * $t);
        $color = imagecolorallocate($img, min(255, $r), max(0, $g), max(0, $b));
        imageline($img, 0, $y, $size, $y, $color);
    }

    $white = imagecolorallocate($img, 255, 255, 255);
    $shadow = imagecolorallocatealpha($img, 120, 20, 50, 90);
    $plateFill = imagecolorallocatealpha($img, 255, 255, 255, 40);

    $cx = (int) ($size / 2);
    $plateCy = (int) ($size * 0.66);
    $rx = (int) ($size * 0.30);
    $ry = (int) ($size * 0.10);

    // Plate shadow
    imagefilledellipse($img, $cx, $plateCy + (int) ($size * 0.045), $rx + 10, $ry + 8, $shadow);
    // Plate
    imagefilledellipse($img, $cx, $plateCy, $rx * 2, $ry * 2, $white);
    // Dome (cloche)
    $domeW = (int) ($size * 0.46);
    $domeH = (int) ($size * 0.46);
    imagefilledarc($img, $cx, $plateCy - (int) ($size * 0.02), $domeW, $domeH, 180, 360, $white, IMG_ARC_PIE);
    imagefilledellipse($img, $cx, $plateCy - (int) ($size * 0.02), $domeW, (int) ($size * 0.06), $plateFill);

    // Knob
    imagefilledellipse($img, $cx, $plateCy - (int) ($size * 0.255), (int) ($size * 0.075), (int) ($size * 0.05), $white);

    // Steam dots
    imagefilledellipse($img, (int) ($cx - $size * 0.155), (int) ($size * 0.30), (int) ($size * 0.030), (int) ($size * 0.030), $plateFill);
    imagefilledellipse($img, (int) ($cx + $size * 0.150), (int) ($size * 0.27), (int) ($size * 0.024), (int) ($size * 0.024), $plateFill);

    if ($rounded) {
        // Round the corners with a mask drawn as 4 background-coloured arcs is not
        // possible on a transparent canvas, so re-draw the gradient only inside a
        // rounded rect mask.
        $mask = imagecreatetruecolor($size, $size);
        imagesavealpha($mask, true);
        $transparent = imagecolorallocatealpha($mask, 0, 0, 0, 127);
        imagefill($mask, 0, 0, $transparent);
        $maskWhite = imagecolorallocatealpha($mask, 255, 255, 255, 0);
        $radius = (int) ($size * 0.22);
        imagefilledrectangle($mask, $radius, 0, $size - $radius, $size, $maskWhite);
        imagefilledrectangle($mask, 0, $radius, $size, $size - $radius, $maskWhite);
        foreach ([[0, 0], [$size - $radius * 2, 0], [0, $size - $radius * 2], [$size - $radius * 2, $size - $radius * 2]] as [$mx, $my]) {
            imagefilledellipse($mask, $mx + $radius, $my + $radius, $radius * 2, $radius * 2, $maskWhite);
        }
        for ($x = 0; $x < $size; $x++) {
            for ($y = 0; $y < $size; $y++) {
                $alpha = (imagecolorat($mask, $x, $y) >> 24) & 0x7F;
                if ($alpha > 100) {
                    imagesetpixel($img, $x, $y, imagecolorallocatealpha($img, 0, 0, 0, 127));
                }
            }
        }
        imagedestroy($mask);
    }

    imagepng($img, $path);
    imagedestroy($img);
    echo '  wrote ' . basename($path) . ' (' . $size . "px)\n";
}

make_icon($iconDir . '/icon-192.png', 192);
make_icon($iconDir . '/icon-512.png', 512);
make_icon($iconDir . '/icon-maskable-512.png', 512, false);
make_icon($iconDir . '/apple-touch-icon.png', 180);

echo "Asset generation complete.\n";
