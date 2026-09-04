<?php
/**
 * Button.
 *
 * Renders either an <a> or a <button> depending on whether an href is given,
 * so link-actions and form-actions share one visual definition.
 *
 * @var string      $label
 * @var string|null $href
 * @var string      $variant   primary|secondary|ghost|accent|danger|inverse
 * @var string      $size      sm|md|lg
 * @var string|null $icon
 * @var string      $iconPosition start|end
 * @var string      $type      submit|button
 * @var bool        $block
 * @var bool        $disabled
 * @var array       $attributes
 */

$label = $label ?? '';
$href = $href ?? null;
$variant = $variant ?? 'primary';
$size = $size ?? 'md';
$icon = $icon ?? null;
$iconPosition = $iconPosition ?? 'start';
$type = $type ?? 'submit';
$block = $block ?? false;
$disabled = $disabled ?? false;
$attributes = $attributes ?? [];

$classes = class_names([
    'btn' => true,
    'btn--' . $variant => $variant !== 'primary',
    'btn--' . $size => $size !== 'md',
    'btn--block' => $block,
    ($attributes['class'] ?? '') => isset($attributes['class']),
]);

unset($attributes['class']);

$iconMarkup = $icon === null ? '' : component('primitives/icon', ['name' => $icon, 'size' => 16]);
$tag = $href === null ? 'button' : 'a';
?>
<<?= $tag ?> class="<?= e($classes) ?>"
    <?= $href === null ? 'type="' . e($type) . '"' : 'href="' . e($href) . '"' ?>
    <?= $disabled ? ($href === null ? 'disabled' : 'aria-disabled="true"') : '' ?>
    <?= attributes($attributes) ?>>
    <?php if ($icon !== null && $iconPosition === 'start'): ?><?= $iconMarkup ?><?php endif; ?>
    <span><?= e($label) ?></span>
    <?php if ($icon !== null && $iconPosition === 'end'): ?><?= $iconMarkup ?><?php endif; ?>
</<?= $tag ?>>
