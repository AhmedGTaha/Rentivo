<?php
/**
 * Agency storefront.
 *
 * The organization's brand colour is applied as an accent hairline and small
 * highlights only; agencies can never inject markup, script or arbitrary CSS.
 *
 * @var array $organization
 * @var array $cars
 * @var int   $fleetCount
 * @var array $locations
 * @var array $favoriteIds
 */

$organization = $organization ?? [];
$cars = $cars ?? [];
$fleetCount = (int) ($fleetCount ?? 0);
$locations = $locations ?? [];
$favoriteIds = $favoriteIds ?? [];

$slug = (string) ($organization['slug'] ?? '');
$logo = $organization['logo_path'] ?? null;
?>

<section class="agency-header">
    <div class="container agency-header__inner">
        <span class="agency-header__logo">
            <?php if ($logo !== null && $logo !== ''): ?>
                <img src="<?= e('/uploads/' . ltrim((string) $logo, '/')) ?>" alt="">
            <?php else: ?>
                <?= e(\Rentivo\Support\Str::initials((string) ($organization['name'] ?? ''))) ?>
            <?php endif; ?>
        </span>

        <div class="grow">
            <p class="eyebrow">Agency storefront</p>
            <h1 class="agency-header__name"><?= e($organization['name'] ?? '') ?></h1>
            <div class="agency-header__meta">
                <?php if (($organization['address'] ?? null) !== null && $organization['address'] !== ''): ?>
                    <span class="row" style="gap: var(--space-2);">
                        <?= component('primitives/icon', ['name' => 'map-pin', 'size' => 15]) ?>
                        <?= e($organization['address']) ?>
                    </span>
                <?php endif; ?>
                <span class="row" style="gap: var(--space-2);">
                    <?= component('primitives/icon', ['name' => 'car', 'size' => 15]) ?>
                    <?= (int) $fleetCount ?> vehicle<?= $fleetCount === 1 ? '' : 's' ?>
                </span>
            </div>
        </div>

        <?= component('primitives/button', [
            'label' => 'Browse this fleet',
            'href'  => '/agency/' . rawurlencode($slug) . '/cars',
            'icon'  => 'arrow-right',
            'iconPosition' => 'end',
        ]) ?>
    </div>
</section>

<div class="container" style="padding-block: var(--space-8) var(--space-11);">
    <div class="layout-sidebar layout-sidebar--wide-rail">
        <aside class="layout-sidebar__rail">
            <div class="card card--padded stack">
                <div>
                    <p class="eyebrow">Contact</p>
                </div>
                <dl class="detail-list detail-list--rows">
                    <?php if (($organization['contact_email'] ?? null) !== null): ?>
                        <div class="detail-list__item">
                            <dt class="detail-list__label">Email</dt>
                            <dd class="detail-list__value">
                                <a href="mailto:<?= e($organization['contact_email']) ?>">
                                    <?= e($organization['contact_email']) ?>
                                </a>
                            </dd>
                        </div>
                    <?php endif; ?>
                    <?php if (($organization['phone'] ?? null) !== null): ?>
                        <div class="detail-list__item">
                            <dt class="detail-list__label">Phone</dt>
                            <dd class="detail-list__value">
                                <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $organization['phone'])) ?>">
                                    <?= e($organization['phone']) ?>
                                </a>
                            </dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>

            <?php if ($locations !== []): ?>
                <div class="card card--padded stack" style="margin-top: var(--space-4);">
                    <p class="eyebrow">Locations</p>
                    <?php foreach ($locations as $location): ?>
                        <div class="location-card" style="padding: var(--space-4); border: none;">
                            <span class="location-card__icon">
                                <?= component('primitives/icon', ['name' => 'map-pin', 'size' => 16]) ?>
                            </span>
                            <div class="grow">
                                <p class="location-card__name"><?= e($location['name']) ?></p>
                                <p class="location-card__meta"><?= e($location['address']) ?></p>
                                <?php if (($location['opening_hours'] ?? null) !== null): ?>
                                    <p class="location-card__meta"><?= e($location['opening_hours']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </aside>

        <div class="grow">
            <?php if (($organization['description'] ?? null) !== null && trim((string) $organization['description']) !== ''): ?>
                <section class="car-section">
                    <h2 class="car-section__title">About <?= e($organization['name'] ?? '') ?></h2>
                    <p class="car-section__body"><?= e($organization['description']) ?></p>
                </section>
            <?php endif; ?>

            <section class="car-section">
                <div class="section-header" style="margin-bottom: var(--space-5);">
                    <h2 class="car-section__title" style="margin-bottom: 0;">Available vehicles</h2>
                    <?php if ($cars !== []): ?>
                        <a class="text-sm text-muted" href="/agency/<?= e(rawurlencode($slug)) ?>/cars">
                            View all <?= (int) $fleetCount ?>
                        </a>
                    <?php endif; ?>
                </div>

                <?= component('cars/car-grid', [
                    'cars'        => $cars,
                    'favoriteIds' => $favoriteIds,
                    'showAgency'  => false,
                    'gridClass'   => 'car-grid car-grid--compact',
                    'emptyState'  => [
                        'title'       => 'No vehicles published yet',
                        'description' => 'This agency has not listed any cars for booking.',
                        'icon'        => 'car',
                    ],
                ]) ?>
            </section>

            <?php if (($organization['rental_terms'] ?? null) !== null && trim((string) $organization['rental_terms']) !== ''): ?>
                <section class="car-section">
                    <h2 class="car-section__title">Rental terms</h2>
                    <div class="car-terms"><?= e($organization['rental_terms']) ?></div>
                </section>
            <?php endif; ?>
        </div>
    </div>
</div>
