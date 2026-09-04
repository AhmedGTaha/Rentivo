<?php
/**
 * Favorite button.
 *
 * A real form posting to /favorites/{slug}/toggle with a CSRF token, so it
 * works with JavaScript disabled. favorite.js upgrades it to a fetch.
 *
 * Guests are not shown a broken control: their submit reaches the server,
 * which sends them through Google and back.
 *
 * @var string $slug
 * @var bool   $isFavorite
 * @var string $carName
 * @var bool   $large
 * @var bool   $bare
 */

$slug = $slug ?? '';
$isFavorite = $isFavorite ?? false;
$carName = $carName ?? 'this car';
$large = $large ?? false;
$bare = $bare ?? false;

$label = $isFavorite
    ? 'Remove ' . $carName . ' from saved cars'
    : 'Save ' . $carName . ' to your favorites';
?>
<form method="post" action="/favorites/<?= e(rawurlencode($slug)) ?>/toggle" data-favorite-form>
    <?= csrf_field() ?>
    <button type="submit"
            class="<?= e(class_names([
                'favorite-btn' => true,
                'favorite-btn--lg' => $large,
                'favorite-btn--bare' => $bare,
            ])) ?>"
            data-favorite-button
            aria-pressed="<?= $isFavorite ? 'true' : 'false' ?>"
            aria-label="<?= e($label) ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M20.8 5.6a5 5 0 0 0-7.1 0L12 7.3l-1.7-1.7a5 5 0 1 0-7.1 7.1l8.8 8.8 8.8-8.8a5 5 0 0 0 0-7.1Z"/>
        </svg>
    </button>
</form>
