<?php
/**
 * Homepage.
 *
 * Hero → discovery → featured fleet → how it works → platform features →
 * featured agencies → closing call to action.
 *
 * Every section degrades gracefully when the platform has no content yet,
 * which matters for a fresh installation.
 *
 * @var array $featuredCars
 * @var array $featuredAgencies
 * @var array $categories
 * @var array $brands
 * @var array $favoriteIds
 */

$featuredCars = $featuredCars ?? [];
$featuredAgencies = $featuredAgencies ?? [];
$categories = $categories ?? [];
$brands = $brands ?? [];
$favoriteIds = $favoriteIds ?? [];

$heroCar = $featuredCars[0] ?? null;
$heroImage = $heroCar['primary_image'] ?? null;
?>

<section class="hero">
    <div class="container hero__grid">
        <div>
            <p class="hero__eyebrow">
                <?= component('primitives/icon', ['name' => 'map-pin', 'size' => 14]) ?>
                Serving the Kingdom of Bahrain
            </p>

            <h1 class="hero__title">
                Find your
                <span class="hero__title-accent">perfect car</span>
            </h1>

            <p class="hero__lead">
                Browse vehicles from trusted rental agencies across Bahrain in one
                premium marketplace. Compare, check availability, and book in minutes —
                no account needed to look around.
            </p>

            <div class="hero__actions">
                <?= component('primitives/button', [
                    'label' => 'Browse cars',
                    'href'  => '/cars',
                    'size'  => 'lg',
                    'icon'  => 'arrow-right',
                    'iconPosition' => 'end',
                ]) ?>
                <?= component('primitives/button', [
                    'label'   => 'View agencies',
                    'href'    => '/agencies',
                    'variant' => 'secondary',
                    'size'    => 'lg',
                ]) ?>
            </div>

            <div class="hero__stats">
                <div>
                    <p class="hero__stat-value"><?= count($featuredAgencies) > 0 ? 'Multi' : 'Open' ?></p>
                    <p class="hero__stat-label">Agency marketplace</p>
                </div>
                <div>
                    <p class="hero__stat-value">BHD</p>
                    <p class="hero__stat-label">Transparent daily rates</p>
                </div>
                <div>
                    <p class="hero__stat-value">24h</p>
                    <p class="hero__stat-label">Rental periods</p>
                </div>
            </div>
        </div>

        <div class="hero__visual">
            <?php if ($heroImage !== null && $heroImage !== ''): ?>
                <img src="<?= e('/uploads/' . ltrim((string) $heroImage, '/')) ?>"
                     alt="<?= e(trim(($heroCar['brand'] ?? '') . ' ' . ($heroCar['model'] ?? ''))) ?>"
                     fetchpriority="high" decoding="async">
                <div class="hero__badge">
                    <div>
                        <p class="hero__badge-title">
                            <?= e(trim(($heroCar['brand'] ?? '') . ' ' . ($heroCar['model'] ?? ''))) ?>
                        </p>
                        <p class="hero__badge-meta"><?= e($heroCar['organization_name'] ?? '') ?></p>
                    </div>
                    <?= component('cars/car-price', ['fils' => (int) ($heroCar['daily_rate_fils'] ?? 0)]) ?>
                </div>
            <?php else: ?>
                <div class="hero__visual-empty" role="img" aria-label="Rentivo marketplace">
                    <?= component('primitives/icon', ['name' => 'car', 'size' => 76]) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($categories !== [] || $brands !== []): ?>
    <section class="section-tight">
        <div class="container">
            <div class="section-header">
                <div>
                    <p class="eyebrow">Start browsing</p>
                    <h2 class="section-header__title">Popular categories and brands</h2>
                </div>
            </div>

            <div class="discovery">
                <?php foreach ($categories as $category): ?>
                    <a class="discovery__link" href="/cars?category=<?= e(rawurlencode((string) $category)) ?>">
                        <?= component('primitives/icon', ['name' => 'car', 'size' => 15]) ?>
                        <?= e($category) ?>
                    </a>
                <?php endforeach; ?>

                <?php foreach ($brands as $brand): ?>
                    <a class="discovery__link" href="/cars?brand=<?= e(rawurlencode((string) $brand)) ?>">
                        <?= e($brand) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <div class="section-header">
            <div>
                <p class="eyebrow">Featured fleet</p>
                <h2 class="section-header__title">Newly listed vehicles</h2>
                <p class="section-header__lead">
                    Fresh arrivals from agencies across the marketplace.
                </p>
            </div>
            <?= component('primitives/button', [
                'label'   => 'See all cars',
                'href'    => '/cars',
                'variant' => 'secondary',
                'icon'    => 'arrow-right',
                'iconPosition' => 'end',
            ]) ?>
        </div>

        <?= component('cars/car-grid', [
            'cars'        => $featuredCars,
            'favoriteIds' => $favoriteIds,
            'emptyState'  => [
                'title'       => 'No vehicles listed yet',
                'description' => 'Once agencies publish their fleets, featured cars will appear here.',
                'icon'        => 'car',
                'actions'     => [
                    ['label' => 'List your fleet', 'href' => '/organizations/create'],
                ],
            ],
        ]) ?>
    </div>
</section>

<section class="section" style="background: var(--color-surface);">
    <div class="container">
        <div class="section-header">
            <div>
                <p class="eyebrow">How it works</p>
                <h2 class="section-header__title">Booking a car takes four steps</h2>
            </div>
        </div>

        <div class="steps">
            <div class="step">
                <span class="step__number">01</span>
                <h3 class="step__title">Browse freely</h3>
                <p class="step__description">
                    Search, filter and compare vehicles from every agency without
                    creating an account.
                </p>
            </div>
            <div class="step">
                <span class="step__number">02</span>
                <h3 class="step__title">Pick your dates</h3>
                <p class="step__description">
                    Choose pickup and return times. Only cars genuinely free for that
                    period are shown.
                </p>
            </div>
            <div class="step">
                <span class="step__number">03</span>
                <h3 class="step__title">Sign in with Google</h3>
                <p class="step__description">
                    One tap, no password. Your selection is preserved and you land
                    straight back at checkout.
                </p>
            </div>
            <div class="step">
                <span class="step__number">04</span>
                <h3 class="step__title">Collect and drive</h3>
                <p class="step__description">
                    The agency confirms your request, then you pay at pickup and
                    collect the keys.
                </p>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-header">
            <div>
                <p class="eyebrow">Why Rentivo</p>
                <h2 class="section-header__title">Built for drivers and agencies alike</h2>
            </div>
        </div>

        <div class="features">
            <div class="feature">
                <span class="feature__icon">
                    <?= component('primitives/icon', ['name' => 'search', 'size' => 20]) ?>
                </span>
                <h3 class="feature__title">One place to compare</h3>
                <p class="feature__description">
                    Every participating agency in a single catalogue, with consistent
                    pricing in BHD and honest availability.
                </p>
            </div>
            <div class="feature">
                <span class="feature__icon">
                    <?= component('primitives/icon', ['name' => 'shield', 'size' => 20]) ?>
                </span>
                <h3 class="feature__title">Documents stay private</h3>
                <p class="feature__description">
                    Your licence and ID are stored outside the public web root and
                    released only to agencies you actually book with.
                </p>
            </div>
            <div class="feature">
                <span class="feature__icon">
                    <?= component('primitives/icon', ['name' => 'building', 'size' => 20]) ?>
                </span>
                <h3 class="feature__title">Real fleet management</h3>
                <p class="feature__description">
                    Agencies run their vehicles, bookings, pickups, returns and staff
                    permissions from one console.
                </p>
            </div>
        </div>
    </div>
</section>

<?php if ($featuredAgencies !== []): ?>
    <section class="section" style="background: var(--color-surface);">
        <div class="container">
            <div class="section-header">
                <div>
                    <p class="eyebrow">Featured agencies</p>
                    <h2 class="section-header__title">Trusted rental partners</h2>
                </div>
                <?= component('primitives/button', [
                    'label'   => 'All agencies',
                    'href'    => '/agencies',
                    'variant' => 'secondary',
                    'icon'    => 'arrow-right',
                    'iconPosition' => 'end',
                ]) ?>
            </div>

            <div class="agency-grid">
                <?php foreach ($featuredAgencies as $agency): ?>
                    <?= component('organizations/agency-card', ['agency' => $agency]) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <div class="cta-panel">
            <p class="eyebrow" style="color: rgba(255,255,255,0.55);">Run a rental agency?</p>
            <h2 class="cta-panel__title">Put your fleet in front of more drivers</h2>
            <p class="cta-panel__lead">
                Create your organization, add your locations and vehicles, invite your
                team with precise permissions, and manage every booking from pickup to
                return.
            </p>
            <div class="cta-panel__actions">
                <?= component('primitives/button', [
                    'label'   => 'Create your organization',
                    'href'    => '/organizations/create',
                    'variant' => 'inverse',
                    'size'    => 'lg',
                ]) ?>
            </div>
        </div>
    </div>
</section>
