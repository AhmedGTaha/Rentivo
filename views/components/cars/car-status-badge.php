<?php
/**
 * Car status badge.
 *
 * @var string $status
 * @var bool   $small
 */

use Rentivo\Services\CarService;

$status = (string) ($status ?? 'available');
$small = $small ?? false;

echo component('primitives/badge', [
    'label' => CarService::statusLabel($status),
    'tone'  => CarService::statusTone($status),
    'dot'   => true,
    'small' => $small,
]);
