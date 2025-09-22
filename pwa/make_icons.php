<?php
// /pwa/make_icons.php — tiny GD generator for PWA icons
// Visit this once in the browser to generate icons into /pwa/icons

function mkIcon(int $size, string $file, string $label='ST', bool $rounded=false) {
  $im = imagecreatetruecolor($size, $size);
  imagesavealpha($im, true);
  $bg = imagecolorallocatealpha($im, 0, 0, 0, 127); // transparent
  imagefill($im, 0, 0, $bg);

  // maskable background (safe area)
  $blue = imagecolorallocate($im, 14, 165, 233);     // #0ea5e9
  $dark = imagecolorallocate($im, 11, 18, 32);       // #0b1220
  if ($rounded) {
    // big circle
    $r = (int)round($size * 0.96);
    imagefilledellipse($im, $size/2, $size/2, $r, $r, $blue);
  } else {
    imagefilledrectangle($im, 0, 0, $size, $size, $blue);
  }

  // inner dark badge
  $pad = (int)round($size * 0.10);
  imagefilledrectangle($im, $pad, $pad, $size-$pad, $size-$pad, $dark);

  // letters
  $white = imagecolorallocate($im, 245, 250, 255);
  $font = __DIR__ . '/DejaVuSans-Bold.ttf';
  $text = $label;
  $fs = (int)round($size * 0.42);
  if (!is_file($font)) {
    // fallback: built-in GD font if TTF not found
    $fw = imagefontwidth(5); $fh = imagefontheight(5);
    $tw = $fw * strlen($text); $th = $fh;
    imagestring($im, 5, (int)(($size-$tw)/2), (int)(($size-$th)/2), $text, $white);
  } else {
    $bbox = imagettfbbox($fs, 0, $font, $text);
    $tw = $bbox[2] - $bbox[0];
    $th = $bbox[1] - $bbox[7];
    $x = (int)(($size - $tw) / 2 - $bbox[0]);
    $y = (int)(($size - $th) / 2 + $th);
    imagettftext($im, $fs, 0, $x, $y, $white, $font, $text);
  }

  imagepng($im, $file);
  imagedestroy($im);
}

$dir = __DIR__ . '/icons';
if (!is_dir($dir)) mkdir($dir, 0775, true);

// square icons
mkIcon(192,  $dir.'/icon-192.png',  'ST', false);
mkIcon(512,  $dir.'/icon-512.png',  'ST', false);
// maskable (round-friendly)
mkIcon(192,  $dir.'/maskable-192.png','ST', true);
mkIcon(512,  $dir.'/maskable-512.png','ST', true);
// apple touch (180)
mkIcon(180,  $dir.'/icon-apple-180.png','ST', true);

echo "OK — generated to /pwa/icons\n";
