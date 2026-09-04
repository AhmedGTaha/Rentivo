<?php
/**
 * Metric card.
 *
 * @var string      $label
 * @var string      $value
 * @var string|null $meta
 * @var string|null $icon
 * @var string|null $href
 * @var string      $variant  default|emphasis|alert
 */

$label = $label ?? '';
$value = $value ?? '0';
$meta = $meta ?? null;
$icon = $icon ?? null;
$href = $href ?? null;
$variant = $variant ?? 'default';

$classes = class_names([
    'metric' => true,
    'metric--emphasis' => $variant === 'emphasis',
    'metric--alert' => $variant === 'alert',
]);

$tag = $href === null ? 'div' : 'a';
?>
<<?= $tag ?> class="<?= e($classes) ?>"<?= $href === null ? '' : ' href="' . e($href) . '"' ?>>
    <span class="metric__label">
        <?php if ($icon !== null): ?>
            <?= component('primitives/icon', ['name' => $icon, 'size' => 14]) ?>
        <?php endif; ?>
        <?= e($label) ?>
    </span>
    <span class="metric__value"><?= e($value) ?></span>
    <?php if ($meta !== null): ?>
        <span class="metric__meta"><?= e($meta) ?></span>
    <?php endif; ?>
</<?= $tag ?>>
