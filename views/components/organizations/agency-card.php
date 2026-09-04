<?php
/**
 * Agency card for the public directory.
 *
 * @var array $agency  Organization row, optionally with fleet_count
 */

$agency = $agency ?? [];

$slug = (string) ($agency['slug'] ?? '');
$logo = $agency['logo_path'] ?? null;
$fleetCount = isset($agency['fleet_count']) ? (int) $agency['fleet_count'] : null;
?>
<article class="agency-card">
    <div class="row" style="gap: var(--space-4);">
        <span class="agency-card__logo">
            <?php if ($logo !== null && $logo !== ''): ?>
                <img src="<?= e('/uploads/' . ltrim((string) $logo, '/')) ?>" alt="" loading="lazy">
            <?php else: ?>
                <?= e(\Rentivo\Support\Str::initials((string) ($agency['name'] ?? ''))) ?>
            <?php endif; ?>
        </span>
        <div class="grow">
            <h3 class="agency-card__name">
                <a href="/agency/<?= e(rawurlencode($slug)) ?>"><?= e($agency['name'] ?? '') ?></a>
            </h3>
            <?php if (($agency['address'] ?? null) !== null && $agency['address'] !== ''): ?>
                <p class="text-xs text-muted truncate"><?= e($agency['address']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (($agency['description'] ?? null) !== null && trim((string) $agency['description']) !== ''): ?>
        <p class="agency-card__description"><?= e($agency['description']) ?></p>
    <?php endif; ?>

    <div class="agency-card__meta">
        <?php if ($fleetCount !== null): ?>
            <span class="row" style="gap: var(--space-2);">
                <?= component('primitives/icon', ['name' => 'car', 'size' => 14]) ?>
                <?= (int) $fleetCount ?> vehicle<?= $fleetCount === 1 ? '' : 's' ?>
            </span>
        <?php endif; ?>
        <span class="row" style="gap: var(--space-2); margin-left: auto;">
            View storefront
            <?= component('primitives/icon', ['name' => 'arrow-right', 'size' => 14]) ?>
        </span>
    </div>
</article>
