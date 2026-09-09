<?php

declare(strict_types=1);

$sourceDir = __DIR__ . '/../portfolio-captures/rentivo/raw';
$targetDir = __DIR__ . '/../portfolio-captures/rentivo/webp';

$files = [
    'rentivo-home',
    'rentivo-browse-cars',
    'rentivo-car-details',
    'rentivo-agency',
    'rentivo-dashboard',
    'rentivo-fleet',
    'rentivo-bookings',
    'rentivo-booking-detail',
    'rentivo-permissions',
    'rentivo-reports',
];

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

foreach ($files as $name) {
    $source = $sourceDir . '/' . $name . '.png';
    $target = $targetDir . '/' . $name . '.webp';

    $image = imagecreatefrompng($source);
    if (!$image) {
        fwrite(STDERR, "Unable to read {$source}\n");
        exit(1);
    }

    imagepalettetotruecolor($image);
    imagealphablending($image, true);
    imagesavealpha($image, true);

    if (!imagewebp($image, $target, 84)) {
        fwrite(STDERR, "Unable to write {$target}\n");
        imagedestroy($image);
        exit(1);
    }

    imagedestroy($image);
    echo "exported {$target}\n";
}
