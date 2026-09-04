<?php
/**
 * Error page.
 *
 * Technical detail is only rendered when APP_DEBUG is enabled; production
 * responses carry a clean message and nothing else. The stack trace, SQL and
 * file paths go to storage/logs instead.
 *
 * @var int             $status
 * @var string          $message
 * @var \Throwable|null $exception  Only supplied in debug mode
 */

$status = (int) ($status ?? 500);
$message = $message ?? '';
$exception = $exception ?? null;

$heading = match ($status) {
    403 => 'Access denied',
    404 => 'Page not found',
    405 => 'Action not allowed',
    429 => 'Too many requests',
    default => 'Something went wrong',
};
?>
<div class="container-narrow" style="padding-block: var(--space-11);">
    <p class="eyebrow"><?= (int) $status ?></p>
    <h1 style="margin-top: var(--space-4); font-size: var(--font-size-display-sm);">
        <?= e($heading) ?>
    </h1>
    <p class="lead" style="margin-top: var(--space-5); max-width: 52ch;">
        <?= e($message) ?>
    </p>

    <div class="btn-group" style="margin-top: var(--space-8);">
        <?= component('primitives/button', ['label' => 'Return home', 'href' => '/']) ?>
        <?= component('primitives/button', [
            'label'   => 'Browse cars',
            'href'    => '/cars',
            'variant' => 'secondary',
        ]) ?>
    </div>

    <?php if ($exception !== null): ?>
        <div class="card card--padded" style="margin-top: var(--space-9);">
            <p class="eyebrow">Debug detail (APP_DEBUG is on)</p>
            <p class="text-strong" style="margin-top: var(--space-3);">
                <?= e($exception::class) ?>
            </p>
            <p class="text-sm text-muted" style="margin-top: var(--space-2);">
                <?= e($exception->getFile() . ':' . $exception->getLine()) ?>
            </p>
            <pre style="margin-top: var(--space-4); overflow-x: auto; font-size: var(--font-size-xs);
                        color: var(--color-text-muted); white-space: pre-wrap;"><?= e($exception->getTraceAsString()) ?></pre>
        </div>
    <?php endif; ?>
</div>
