<?php
/**
 * Pickup/return datetime pair.
 *
 * Rendered as one component because the two fields are always used together
 * and share the ordering rule enforced by filters.js and, authoritatively, by
 * the server.
 *
 * @var string $startName
 * @var string $endName
 * @var string $startValue  datetime-local format
 * @var string $endValue
 * @var string $startLabel
 * @var string $endLabel
 * @var array  $errors
 */

$startName = $startName ?? 'pickup_at';
$endName = $endName ?? 'return_at';
$startValue = $startValue ?? '';
$endValue = $endValue ?? '';
$startLabel = $startLabel ?? 'Pickup';
$endLabel = $endLabel ?? 'Return';
$errors = $errors ?? [];
$required = $required ?? false;
?>
<div class="filter-panel__row" data-date-range>
    <?= component('forms/field', [
        'name'       => $startName,
        'label'      => $startLabel,
        'type'       => 'datetime-local',
        'value'      => $startValue,
        'errors'     => $errors,
        'required'   => $required,
        'attributes' => ['data-range-start' => true],
    ]) ?>

    <?= component('forms/field', [
        'name'       => $endName,
        'label'      => $endLabel,
        'type'       => 'datetime-local',
        'value'      => $endValue,
        'errors'     => $errors,
        'required'   => $required,
        'attributes' => ['data-range-end' => true],
    ]) ?>
</div>
