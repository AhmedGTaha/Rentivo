<?php
/**
 * Booking status badge.
 *
 * Label and tone both come from BookingStatus, so the state machine is the
 * single source of truth for how a status is presented.
 *
 * @var string $status
 * @var bool   $small
 */

use Rentivo\Services\BookingStatus;

$status = (string) ($status ?? BookingStatus::PENDING);
$small = $small ?? false;

echo component('primitives/badge', [
    'label' => BookingStatus::label($status),
    'tone'  => BookingStatus::tone($status),
    'dot'   => true,
    'small' => $small,
]);
