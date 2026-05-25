<?php
/**
 * Genera iconos y splash placeholder para un tenant.
 *
 * Uso:
 *   php scripts/make-placeholder-assets.php <slug> <hexColor> <initials>
 *
 * Crea:
 *   tenants/<slug>/icon.png   (1024×1024, color sólido + iniciales)
 *   tenants/<slug>/splash.png (2732×2732, color sólido + iniciales centradas)
 */

$slug = $argv[1] ?? 'demo';
$hex = ltrim($argv[2] ?? '#1E40AF', '#');
$initials = $argv[3] ?? 'LF';

$r = hexdec(substr($hex, 0, 2));
$g = hexdec(substr($hex, 2, 2));
$b = hexdec(substr($hex, 4, 2));

$baseDir = __DIR__ . '/../tenants/' . $slug;
if (! is_dir($baseDir)) {
    mkdir($baseDir, 0755, true);
}

function makePng($filename, $size, array $bg, array $fg, string $text): void
{
    $img = imagecreatetruecolor($size, $size);
    $bgColor = imagecolorallocate($img, $bg[0], $bg[1], $bg[2]);
    imagefilledrectangle($img, 0, 0, $size, $size, $bgColor);

    $fgColor = imagecolorallocate($img, $fg[0], $fg[1], $fg[2]);
    $fontSize = (int) ($size / 3);

    // Texto centrado con la fuente built-in (5 = más grande disponible sin TTF).
    $charWidth = imagefontwidth(5) * 8;  // escalado virtual
    $charHeight = imagefontheight(5) * 8;
    $textWidth = strlen($text) * $charWidth;
    $x = (int) (($size - $textWidth) / 2);
    $y = (int) (($size - $charHeight) / 2);

    // Como imagestring no escala fuentes, simulamos texto grande dibujando un círculo
    // con la inicial al centro usando imagettftext si hay font, o un círculo plano.
    $cx = $size / 2;
    $cy = $size / 2;
    $radius = (int) ($size * 0.35);
    imagefilledellipse($img, $cx, $cy, $radius * 2, $radius * 2, $fgColor);

    // Letra blanca al centro del círculo (font 5 es el más grande built-in).
    $tw = imagefontwidth(5) * strlen($text);
    $th = imagefontheight(5);
    imagestring($img, 5, (int) ($cx - $tw / 2), (int) ($cy - $th / 2), $text, $bgColor);

    imagepng($img, $filename, 9);
    imagedestroy($img);
    echo "  · $filename ({$size}×{$size})\n";
}

$bg = [$r, $g, $b];
$fg = [255, 255, 255]; // blanco

echo "Generando placeholders para tenant '$slug'...\n";
makePng("$baseDir/icon.png", 1024, $bg, $fg, $initials);
makePng("$baseDir/splash.png", 2732, $bg, $fg, $initials);

echo "✓ Listo.\n";
