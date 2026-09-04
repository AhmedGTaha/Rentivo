<?php
/**
 * Avatar.
 *
 * Falls back through: uploaded profile image, Google avatar, initials.
 *
 * @var string|null $imageUrl
 * @var string      $name
 * @var string      $size     sm|md|lg|xl
 * @var bool        $square
 * @var bool        $inverse
 */

$imageUrl = $imageUrl ?? null;
$name = $name ?? '';
$size = $size ?? 'md';
$square = $square ?? false;
$inverse = $inverse ?? false;
?>
<span class="<?= e(class_names([
    'avatar' => true,
    'avatar--' . $size => $size !== 'md',
    'avatar--square' => $square,
    'avatar--inverse' => $inverse,
])) ?>">
    <?php if ($imageUrl !== null && $imageUrl !== ''): ?>
        <img src="<?= e($imageUrl) ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
    <?php else: ?>
        <?= e(\Rentivo\Support\Str::initials($name)) ?>
    <?php endif; ?>
</span>
