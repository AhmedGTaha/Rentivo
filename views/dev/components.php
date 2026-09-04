<?php
/**
 * Development component gallery.
 *
 * Reachable only when APP_ENV is local/development — DevController returns 404
 * otherwise, so this page can never be served in production.
 *
 * Every example renders the real component with fabricated props, so the
 * gallery cannot drift from what the application actually uses.
 */

use Rentivo\Security\Permissions;

$sampleCar = [
    'id'                     => 1,
    'slug'                   => 'demo-car',
    'brand'                  => 'Mercedes-Benz',
    'model'                  => 'E-Class',
    'year'                   => 2024,
    'seats'                  => 5,
    'doors'                  => 4,
    'transmission'           => 'automatic',
    'fuel_type'              => 'petrol',
    'color'                  => 'Obsidian Black',
    'daily_rate_fils'        => 45000,
    'status'                 => 'available',
    'category_name'          => 'Luxury',
    'organization_name'      => 'Gulf Prestige Rentals',
    'organization_slug'      => 'gulf-prestige-rentals',
    'organization_logo_path' => null,
    'primary_image'          => null,
];

$sampleBooking = [
    'id'                       => 1,
    'reference'                => 'BK-2026-000042',
    'brand'                    => 'Toyota',
    'model'                    => 'Corolla',
    'year'                     => 2024,
    'car_slug'                 => 'demo-car',
    'organization_name'        => 'Manama Motors',
    'organization_slug'        => 'manama-motors',
    'customer_name'            => 'Yusuf Rahman',
    'status'                   => 'confirmed',
    'payment_status'           => 'unpaid',
    'pickup_at'                => gmdate('Y-m-d H:i:s', time() + 86400),
    'return_at'                => gmdate('Y-m-d H:i:s', time() + 345600),
    'created_at'               => gmdate('Y-m-d H:i:s', time() - 7200),
    'confirmed_at'             => gmdate('Y-m-d H:i:s', time() - 3600),
    'completed_at'             => null,
    'cancelled_at'             => null,
    'rental_days'              => 3,
    'daily_rate_snapshot_fils' => 12000,
    'subtotal_fils'            => 36000,
    'additional_charges_fils'  => 0,
    'total_fils'               => 36000,
    'primary_image'            => null,
];

$section = static function (string $title, string $note = ''): void {
    echo '<div class="section-header" style="margin-top: var(--space-10);">'
        . '<div><p class="eyebrow">Component</p>'
        . '<h2 class="section-header__title">' . e($title) . '</h2>'
        . ($note === '' ? '' : '<p class="section-header__lead">' . e($note) . '</p>')
        . '</div></div>';
};
?>

<div class="container" style="padding-block: var(--space-9) var(--space-12);">
    <p class="eyebrow">Development only</p>
    <h1 style="font-size: var(--font-size-display-sm); margin-top: var(--space-3);">
        Component gallery
    </h1>
    <p class="lead" style="max-width: 62ch; margin-top: var(--space-4);">
        Every reusable component rendered with sample data. This page is served
        only in local and development environments.
    </p>

    <?php $section('Buttons', 'Variants, sizes and states share one base class.'); ?>
    <div class="card card--padded stack">
        <div class="btn-group">
            <?= component('primitives/button', ['label' => 'Primary']) ?>
            <?= component('primitives/button', ['label' => 'Secondary', 'variant' => 'secondary']) ?>
            <?= component('primitives/button', ['label' => 'Ghost', 'variant' => 'ghost']) ?>
            <?= component('primitives/button', ['label' => 'Danger', 'variant' => 'danger']) ?>
            <?= component('primitives/button', ['label' => 'Accent', 'variant' => 'accent']) ?>
        </div>
        <div class="btn-group">
            <?= component('primitives/button', ['label' => 'Small', 'size' => 'sm']) ?>
            <?= component('primitives/button', ['label' => 'Medium']) ?>
            <?= component('primitives/button', ['label' => 'Large', 'size' => 'lg']) ?>
            <?= component('primitives/button', ['label' => 'With icon', 'icon' => 'plus']) ?>
            <?= component('primitives/button', ['label' => 'Disabled', 'disabled' => true]) ?>
        </div>
        <div class="btn-group">
            <button type="button" class="icon-btn icon-btn--bordered" aria-label="Search">
                <?= component('primitives/icon', ['name' => 'search']) ?>
            </button>
            <button type="button" class="icon-btn icon-btn--bordered icon-btn--sm" aria-label="Edit">
                <?= component('primitives/icon', ['name' => 'edit', 'size' => 16]) ?>
            </button>
            <button type="button" class="icon-btn icon-btn--bordered icon-btn--danger" aria-label="Delete">
                <?= component('primitives/icon', ['name' => 'trash']) ?>
            </button>
        </div>
    </div>

    <?php $section('Badges'); ?>
    <div class="card card--padded">
        <div class="row row-wrap">
            <?php foreach (['neutral', 'success', 'warning', 'danger', 'info', 'ink', 'outline'] as $tone): ?>
                <?= component('primitives/badge', ['label' => ucfirst($tone), 'tone' => $tone, 'dot' => true]) ?>
            <?php endforeach; ?>
        </div>
    </div>

    <?php $section('Form controls'); ?>
    <div class="card card--padded">
        <div class="form-grid form-grid--2">
            <?= component('forms/field', [
                'name' => 'demo_text', 'label' => 'Text field', 'value' => 'Toyota',
                'hint' => 'A short helper message.',
            ]) ?>
            <?= component('forms/field', [
                'name' => 'demo_error', 'label' => 'With an error', 'value' => 'oops',
                'errors' => ['demo_error' => 'This value is not valid.'],
            ]) ?>
            <?= component('forms/field', [
                'name' => 'demo_select', 'label' => 'Select', 'type' => 'select',
                'value' => 'b', 'options' => ['a' => 'Option A', 'b' => 'Option B'],
            ]) ?>
            <?= component('forms/field', [
                'name' => 'demo_money', 'label' => 'Money', 'value' => '25.500', 'prefix' => 'BHD',
            ]) ?>
            <div class="form-grid__full">
                <?= component('forms/field', [
                    'name' => 'demo_area', 'label' => 'Text area', 'type' => 'textarea',
                    'value' => 'A longer description.',
                ]) ?>
            </div>
            <?= component('forms/search-field', ['name' => 'demo_search', 'value' => '']) ?>
            <?= component('forms/price-range', ['minValue' => '10', 'maxValue' => '60']) ?>
        </div>

        <div class="divider"></div>

        <div class="row row-wrap" style="gap: var(--space-6);">
            <?= component('forms/checkbox', ['name' => 'demo_check', 'label' => 'A checkbox', 'checked' => true]) ?>
            <?= component('forms/toggle', ['name' => 'demo_toggle', 'label' => 'A toggle', 'checked' => true]) ?>
        </div>

        <div style="margin-top: var(--space-6);">
            <?= component('forms/datetime-field', ['startValue' => '', 'endValue' => '']) ?>
        </div>

        <div style="margin-top: var(--space-6);">
            <?= component('forms/file-upload', ['name' => 'demo_file']) ?>
        </div>
    </div>

    <?php $section('Alerts and states'); ?>
    <div class="stack">
        <?php foreach (['success', 'info', 'warning', 'error'] as $type): ?>
            <?= component('feedback/alert', [
                'type'        => $type,
                'title'       => ucfirst($type),
                'message'     => 'A ' . $type . ' message explaining what happened.',
                'dismissible' => true,
            ]) ?>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-2" style="margin-top: var(--space-6);">
        <?= component('feedback/empty-state', [
            'title'       => 'No cars found',
            'description' => 'Try widening your filters or clearing your search.',
            'icon'        => 'car',
            'actions'     => [['label' => 'Clear filters', 'href' => '#', 'variant' => 'secondary']],
        ]) ?>
        <?= component('feedback/empty-state', [
            'title'       => 'Something went wrong',
            'description' => 'We could not load this section. Please try again.',
            'icon'        => 'alert',
            'isError'     => true,
        ]) ?>
    </div>

    <?php $section('Loading states'); ?>
    <div class="card card--padded">
        <div class="row" style="gap: var(--space-6); margin-bottom: var(--space-6);">
            <span class="spinner spinner--sm"></span>
            <span class="spinner"></span>
            <span class="spinner spinner--lg"></span>
        </div>
        <div style="max-width: 280px;">
            <div class="skeleton skeleton--image"></div>
            <div class="skeleton skeleton--text" style="width: 70%; margin-top: var(--space-4);"></div>
            <div class="skeleton skeleton--text" style="width: 45%;"></div>
        </div>
    </div>

    <?php $section('Car cards', 'Three variants of one component.'); ?>
    <div class="car-grid">
        <?= component('cars/car-card', ['car' => $sampleCar, 'variant' => 'compact']) ?>
        <?= component('cars/car-card', ['car' => $sampleCar]) ?>
        <?= component('cars/car-card', ['car' => $sampleCar, 'variant' => 'featured', 'isFavorite' => true]) ?>
    </div>

    <?php $section('Car specifications'); ?>
    <div class="card card--padded">
        <?= component('cars/car-specifications', ['car' => $sampleCar]) ?>
    </div>

    <?php $section('Booking components'); ?>
    <div class="grid grid-2">
        <?= component('bookings/booking-card', ['booking' => $sampleBooking, 'href' => '#']) ?>
        <?= component('bookings/booking-summary', [
            'car'      => $sampleCar,
            'pickupAt' => '10 Sep 2026, 10:00',
            'returnAt' => '13 Sep 2026, 10:00',
            'quote'    => [
                'daily_rate_fils'         => 12000,
                'rental_days'             => 3,
                'subtotal_fils'           => 36000,
                'additional_charges_fils' => 0,
                'total_fils'              => 36000,
            ],
        ]) ?>
    </div>

    <div class="card card--padded" style="margin-top: var(--space-5);">
        <div class="row row-wrap" style="margin-bottom: var(--space-6);">
            <?php foreach (['pending', 'confirmed', 'ready_for_pickup', 'active',
                            'completed', 'cancelled', 'rejected', 'no_show'] as $status): ?>
                <?= component('bookings/booking-status-badge', ['status' => $status]) ?>
            <?php endforeach; ?>
        </div>

        <?= component('bookings/booking-timeline', ['booking' => $sampleBooking]) ?>
    </div>

    <?php $section('Dashboard'); ?>
    <div class="grid grid-4">
        <?= component('dashboard/metric-card', ['label' => 'Total cars', 'value' => '24', 'icon' => 'car']) ?>
        <?= component('dashboard/metric-card', ['label' => 'Available', 'value' => '17', 'icon' => 'check-circle']) ?>
        <?= component('dashboard/metric-card', [
            'label' => 'Overdue', 'value' => '2', 'icon' => 'alert', 'variant' => 'alert',
        ]) ?>
        <?= component('dashboard/metric-card', [
            'label' => 'This month', 'value' => '4,820.000', 'meta' => 'BHD',
            'icon' => 'chart', 'variant' => 'emphasis',
        ]) ?>
    </div>

    <div class="card card--padded" style="margin-top: var(--space-5);">
        <?= component('dashboard/activity-item', [
            'entry' => [
                'action_key'    => 'booking.confirmed',
                'actor_name'    => 'Layla Al Mansoor',
                'created_at'    => gmdate('Y-m-d H:i:s', time() - 1800),
                'entity_type'   => 'booking',
                'entity_id'     => 42,
                'metadata_json' => '{"reference":"BK-2026-000042"}',
            ],
            'showMetadata' => true,
        ]) ?>
    </div>

    <?php $section('Navigation'); ?>
    <div class="card card--padded stack-lg stack">
        <?= component('navigation/breadcrumbs', ['items' => [
            ['label' => 'Cars', 'href' => '#'],
            ['label' => 'Manama Motors', 'href' => '#'],
            ['label' => 'Toyota Corolla'],
        ]]) ?>

        <?= component('navigation/tabs', ['items' => [
            ['label' => 'Upcoming', 'href' => '#', 'count' => 3, 'active' => true],
            ['label' => 'Active', 'href' => '#', 'count' => 1],
            ['label' => 'Completed', 'href' => '#', 'count' => 12],
        ]]) ?>

        <?= component('navigation/pagination', [
            'pagination' => new \Rentivo\Support\Pagination(3, 12, 240),
            'path'       => '#',
            'query'      => [],
        ]) ?>
    </div>

    <?php $section('Tables'); ?>
    <div class="table-wrap table-wrap--stack">
        <table class="data-table data-table--stack">
            <caption class="visually-hidden">Sample data table</caption>
            <thead>
                <tr>
                    <th scope="col">Vehicle</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="data-table__numeric">Daily rate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ([['Toyota Corolla', 'available', 12000],
                                ['Nissan X-Trail', 'rented', 22000],
                                ['Toyota Hiace', 'maintenance', 28000]] as [$name, $status, $rate]): ?>
                    <tr>
                        <td data-label="Vehicle"><span class="data-table__primary"><?= e($name) ?></span></td>
                        <td data-label="Status">
                            <?= component('cars/car-status-badge', ['status' => $status, 'small' => true]) ?>
                        </td>
                        <td data-label="Daily rate" class="data-table__numeric">
                            <?= e(\Rentivo\Support\Currency::format($rate)) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php $section('Permission matrix'); ?>
    <?= component('organizations/permission-matrix', [
        'permissionGroups' => Permissions::groups(),
        'assigned'         => [Permissions::CARS_VIEW, Permissions::BOOKINGS_VIEW, Permissions::BOOKINGS_CONFIRM],
    ]) ?>

    <?php $section('Dialogs and drawers'); ?>
    <div class="card card--padded">
        <div class="btn-group">
            <button type="button" class="btn" data-modal-open="demo-modal">Open modal</button>
            <button type="button" class="btn btn--secondary" data-drawer-open="demo-drawer"
                    aria-expanded="false">Open drawer</button>
            <button type="button" class="btn btn--secondary"
                    onclick="window.Rentivo.toast('Saved successfully.', 'success')">Show toast</button>
        </div>

        <div class="dropdown" data-dropdown style="margin-top: var(--space-4);">
            <button type="button" class="btn btn--secondary" data-dropdown-toggle>
                Dropdown
                <?= component('primitives/icon', ['name' => 'chevron-down', 'size' => 14]) ?>
            </button>
            <div class="dropdown__menu dropdown__menu--left" role="menu">
                <p class="dropdown__label">Actions</p>
                <a class="dropdown__item" href="#" role="menuitem">
                    <?= component('primitives/icon', ['name' => 'edit', 'size' => 16]) ?> Edit
                </a>
                <div class="dropdown__divider"></div>
                <a class="dropdown__item dropdown__item--danger" href="#" role="menuitem">
                    <?= component('primitives/icon', ['name' => 'trash', 'size' => 16]) ?> Delete
                </a>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="demo-modal" hidden role="dialog" aria-modal="true" aria-labelledby="demo-modal-title">
    <div class="modal__backdrop" data-overlay-dismiss></div>
    <div class="modal__dialog" data-overlay-panel role="document">
        <button type="button" class="icon-btn modal__close" data-modal-close aria-label="Close dialog">
            <?= component('primitives/icon', ['name' => 'x']) ?>
        </button>
        <div class="modal__header">
            <h2 class="modal__title" id="demo-modal-title">Confirm this action</h2>
            <p class="modal__description">
                Modals trap focus, close on Escape and restore focus to the trigger.
            </p>
        </div>
        <div class="modal__actions">
            <button type="button" class="btn btn--secondary" data-modal-close>Cancel</button>
            <button type="button" class="btn" data-modal-close data-autofocus>Confirm</button>
        </div>
    </div>
</div>

<div class="drawer" id="demo-drawer" hidden role="dialog" aria-modal="true" aria-label="Demo drawer">
    <div class="drawer__backdrop" data-overlay-dismiss></div>
    <div class="drawer__panel" data-overlay-panel>
        <div class="drawer__header">
            <p class="drawer__title">Filters</p>
            <button type="button" class="icon-btn" data-drawer-close aria-label="Close drawer">
                <?= component('primitives/icon', ['name' => 'x']) ?>
            </button>
        </div>
        <div class="drawer__body">
            <p class="text-sm text-muted">
                The browse page renders the same filter panel here on small screens.
            </p>
        </div>
    </div>
</div>
