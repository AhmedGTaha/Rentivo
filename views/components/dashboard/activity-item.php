<?php
/**
 * Activity feed item.
 *
 * Audit entries are immutable records; this component only presents them.
 *
 * @var array $entry       activity_logs row
 * @var bool  $showMetadata
 */

use Rentivo\Services\AuditService;

$entry = $entry ?? [];
$showMetadata = $showMetadata ?? false;

$action = (string) ($entry['action_key'] ?? '');

$icon = match (true) {
    str_starts_with($action, 'booking.')      => 'calendar',
    str_starts_with($action, 'car.')          => 'car',
    str_starts_with($action, 'employee.')     => 'users',
    str_starts_with($action, 'document.')     => 'file-text',
    str_starts_with($action, 'organization.') => 'building',
    str_starts_with($action, 'rental.')       => 'key',
    str_starts_with($action, 'location.')     => 'map-pin',
    str_starts_with($action, 'customer.')     => 'user',
    default                                   => 'activity',
};

$metadata = null;

if ($showMetadata && ($entry['metadata_json'] ?? null) !== null) {
    $decoded = json_decode((string) $entry['metadata_json'], true);

    if (is_array($decoded) && $decoded !== []) {
        $metadata = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
?>
<div class="activity-item">
    <span class="activity-item__icon">
        <?= component('primitives/icon', ['name' => $icon, 'size' => 16]) ?>
    </span>
    <div class="activity-item__body">
        <p class="activity-item__title"><?= e(AuditService::label($action)) ?></p>
        <p class="activity-item__meta">
            <?= e($entry['actor_name'] ?? 'System') ?>
            · <?= e(relative_time((string) ($entry['created_at'] ?? ''))) ?>
            <?php if (($entry['entity_type'] ?? null) !== null): ?>
                · <?= e($entry['entity_type']) ?> #<?= (int) ($entry['entity_id'] ?? 0) ?>
            <?php endif; ?>
        </p>
        <?php if ($metadata !== null): ?>
            <p class="activity-item__metadata"><?= e($metadata) ?></p>
        <?php endif; ?>
    </div>
</div>
