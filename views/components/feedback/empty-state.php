<?php
/**
 * Empty and error states.
 *
 * One component covers "nothing here yet", "nothing matched" and "something
 * went wrong", so every list in the product ends the same way.
 *
 * @var string      $title
 * @var string|null $description
 * @var string      $icon
 * @var array       $actions  List of ['label' => ..., 'href' => ..., 'variant' => ...]
 * @var bool        $isError
 * @var bool        $flush    Renders without its own surface.
 */

$title = $title ?? 'Nothing here yet';
$description = $description ?? null;
$icon = $icon ?? 'inbox';
$actions = $actions ?? [];
$isError = $isError ?? false;
$flush = $flush ?? false;
?>
<div class="<?= e(class_names([
    'state' => true,
    'state--error' => $isError,
    'state--flush' => $flush,
])) ?>">
    <div class="state__icon">
        <?= component('primitives/icon', ['name' => $icon, 'size' => 24]) ?>
    </div>
    <p class="state__title"><?= e($title) ?></p>
    <?php if ($description !== null): ?>
        <p class="state__description"><?= e($description) ?></p>
    <?php endif; ?>
    <?php if ($actions !== []): ?>
        <div class="state__actions">
            <?php foreach ($actions as $action): ?>
                <?= component('primitives/button', [
                    'label'   => $action['label'],
                    'href'    => $action['href'] ?? null,
                    'variant' => $action['variant'] ?? 'primary',
                    'icon'    => $action['icon'] ?? null,
                ]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
