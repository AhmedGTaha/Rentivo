<?php

declare(strict_types=1);

/**
 * Rentivo scheduled tasks.
 *
 * Intended to run hourly from cron:
 *
 *   0 * * * * cd /path/to/rentivo && php scripts/scheduler.php >> storage/logs/scheduler.log 2>&1
 *
 * The run is idempotent: reminders carry a unique dedupe key, so running it
 * twice in the same hour — or catching up after downtime — never produces a
 * duplicate notification.
 *
 *   php scripts/scheduler.php           Run every task
 *   php scripts/scheduler.php --quiet   Only report failures
 */

use Rentivo\Bootstrap;
use Rentivo\Repositories\DocumentRepository;
use Rentivo\Services\SchedulerService;
use Rentivo\Support\Logger;

if (PHP_SAPI !== 'cli') {
    exit('This script must be run from the command line.');
}

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

$app = Bootstrap::boot($basePath);

$quiet = in_array('--quiet', array_slice($argv, 1), true);

$write = static function (string $message, string $color = "\033[36m") use ($quiet): void {
    if (!$quiet) {
        echo $color . $message . "\033[0m" . PHP_EOL;
    }
};

$startedAt = microtime(true);

try {
    /** @var SchedulerService $scheduler */
    $scheduler = $app->get(SchedulerService::class);

    $write('Running scheduled tasks at ' . gmdate('Y-m-d H:i:s') . ' UTC');

    $result = $scheduler->run();

    // Verified reviews lapse once the document's expiry date passes.
    /** @var DocumentRepository $documents */
    $documents = $app->get(DocumentRepository::class);
    $result['expired_document_reviews'] = $documents->expireOutdatedReviews();

    foreach ($result as $task => $count) {
        $write(sprintf('  %-26s %d', str_replace('_', ' ', $task), $count));
    }

    $elapsed = round((microtime(true) - $startedAt) * 1000);
    $write('Completed in ' . $elapsed . 'ms.', "\033[32m");

    // A summary line lands in the log so cron output is not the only record.
    Logger::info('Scheduler run completed.', $result + ['duration_ms' => $elapsed]);

    exit(0);
} catch (Throwable $e) {
    Logger::exception($e, ['context' => 'scheduler']);

    // Always report a failure, even when quiet, so cron mail is triggered.
    fwrite(STDERR, "\033[31mScheduler failed: " . $e->getMessage() . "\033[0m" . PHP_EOL);

    exit(1);
}
