<?php
/**
 * Public footer.
 *
 * @var string $appName
 * @var bool   $isLocal
 */

$appName = $appName ?? 'Rentivo';
$isLocal = $isLocal ?? false;
?>
<footer class="site-footer">
    <div class="container">
        <div class="site-footer__grid">
            <div>
                <p class="site-footer__brand"><?= e($appName) ?></p>
                <p class="site-footer__tagline">
                    A premium marketplace connecting drivers with trusted rental agencies
                    across Bahrain.
                </p>
            </div>

            <div>
                <p class="site-footer__heading">Browse</p>
                <ul class="site-footer__list">
                    <li><a href="/cars">All cars</a></li>
                    <li><a href="/agencies">Agencies</a></li>
                    <li><a href="/cars?sort=price_asc">Best value</a></li>
                    <li><a href="/cars?sort=popular">Most popular</a></li>
                </ul>
            </div>

            <div>
                <p class="site-footer__heading">Account</p>
                <ul class="site-footer__list">
                    <li><a href="/account">Your account</a></li>
                    <li><a href="/account/bookings">Bookings</a></li>
                    <li><a href="/account/favorites">Saved cars</a></li>
                    <li><a href="/account/documents">Documents</a></li>
                </ul>
            </div>

            <div>
                <p class="site-footer__heading">For agencies</p>
                <ul class="site-footer__list">
                    <li><a href="/organizations/create">List your fleet</a></li>
                    <li><a href="/agencies">Browse agencies</a></li>
                    <?php if ($isLocal): ?>
                        <li><a href="/dev/components">Component gallery</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="site-footer__bottom">
            <p>© <?= e(date('Y')) ?> <?= e($appName) ?>. All rights reserved.</p>
            <p>Prices shown in Bahraini Dinar (BHD).</p>
        </div>
    </div>
</footer>
