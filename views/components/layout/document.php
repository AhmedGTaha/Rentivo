<?php
/**
 * HTML document shell.
 *
 * The single place that emits <head>, stylesheet links and the script tag.
 * Every layout composes this rather than repeating page chrome.
 *
 * @var string      $body            Already-rendered page body
 * @var string      $title
 * @var string|null $metaDescription
 * @var string      $appName
 * @var string|null $accentColor     Validated hex colour, or null
 * @var string      $bodyClass
 * @var bool        $includeManagementStyles
 */

$body = $body ?? '';
$title = $title ?? 'Rentivo';
$metaDescription = $metaDescription ?? 'Browse and book cars from trusted rental agencies across Bahrain.';
$appName = $appName ?? 'Rentivo';
$accentColor = $accentColor ?? null;
$bodyClass = $bodyClass ?? '';
$includeManagementStyles = $includeManagementStyles ?? false;

$stylesheets = [
    'assets/css/tokens.css',
    'assets/css/base.css',
    'assets/css/layout.css',
    'assets/css/components/buttons.css',
    'assets/css/components/forms.css',
    'assets/css/components/cards.css',
    'assets/css/components/badges.css',
    'assets/css/components/navigation.css',
    'assets/css/components/car-card.css',
    'assets/css/components/feedback.css',
    'assets/css/components/tables.css',
    'assets/css/components/booking.css',
    'assets/css/pages/home.css',
    'assets/css/pages/browse.css',
    'assets/css/pages/car-details.css',
    'assets/css/pages/account.css',
];

if ($includeManagementStyles) {
    $stylesheets[] = 'assets/css/pages/management.css';
}

// Only a validated #rrggbb value ever reaches the accent variable.
$safeAccent = is_string($accentColor) && preg_match('/^#[0-9a-fA-F]{6}$/', $accentColor) === 1
    ? strtolower($accentColor)
    : null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e($appName) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta name="color-scheme" content="light">
    <meta name="referrer" content="strict-origin-when-cross-origin">

    <?php foreach ($stylesheets as $stylesheet): ?>
        <link rel="stylesheet" href="<?= e(asset($stylesheet)) ?>">
    <?php endforeach; ?>

    <?php if ($safeAccent !== null): ?>
        <style>:root { --color-accent: <?= e($safeAccent) ?>; --color-accent-contrast: #ffffff; }</style>
    <?php endif; ?>

    <script type="module" src="<?= e(asset('assets/js/app.js')) ?>"></script>
</head>
<body<?= $bodyClass === '' ? '' : ' class="' . e($bodyClass) . '"' ?>>
    <a class="skip-link" href="#main">Skip to content</a>
    <?= $body ?>
</body>
</html>
