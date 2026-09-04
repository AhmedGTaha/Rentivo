<?php
/**
 * Alert.
 *
 * @var string      $type     info|success|warning|error
 * @var string      $message
 * @var string|null $title
 * @var bool        $dismissible
 */

$type = $type ?? 'info';
$message = $message ?? '';
$title = $title ?? null;
$dismissible = $dismissible ?? false;

$icon = match ($type) {
    'success' => 'check-circle',
    'warning' => 'alert',
    'error', 'danger' => 'x-circle',
    default   => 'info',
};
?>
<div class="alert alert--<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
    <?= component('primitives/icon', ['name' => $icon, 'class' => 'alert__icon', 'size' => 18]) ?>
    <div class="alert__body">
        <?php if ($title !== null): ?>
            <p class="alert__title"><?= e($title) ?></p>
        <?php endif; ?>
        <p class="alert__message"><?= e($message) ?></p>
    </div>
    <?php if ($dismissible): ?>
        <button type="button" class="alert__dismiss" data-alert-dismiss aria-label="Dismiss message">
            <?= component('primitives/icon', ['name' => 'x', 'size' => 16]) ?>
        </button>
    <?php endif; ?>
</div>
