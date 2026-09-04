<?php
/**
 * Flash message region.
 *
 * Server-rendered so feedback survives the redirect-after-POST pattern the
 * whole application uses, and works without JavaScript.
 *
 * @var array $flashMessages List of ['type' => ..., 'message' => ...]
 */

$flashMessages = $flashMessages ?? [];

if ($flashMessages === []) {
    return;
}
?>
<div class="stack-sm stack" style="margin-bottom: var(--space-6);" role="region" aria-label="Notifications">
    <?php foreach ($flashMessages as $flash): ?>
        <?= component('feedback/alert', [
            'type'        => $flash['type'] ?? 'info',
            'message'     => $flash['message'] ?? '',
            'dismissible' => true,
        ]) ?>
    <?php endforeach; ?>
</div>
