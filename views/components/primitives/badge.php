<?php
/**
 * Badge.
 *
 * @var string $label
 * @var string $tone   neutral|success|warning|danger|info|ink|outline|accent
 * @var bool   $dot
 * @var bool   $small
 */

$label = $label ?? '';
$tone = $tone ?? 'neutral';
$dot = $dot ?? false;
$small = $small ?? false;
?>
<span class="<?= e(class_names([
    'badge' => true,
    'badge--' . $tone => true,
    'badge--sm' => $small,
])) ?>">
    <?php if ($dot): ?><span class="badge__dot" aria-hidden="true"></span><?php endif; ?>
    <?= e($label) ?>
</span>
