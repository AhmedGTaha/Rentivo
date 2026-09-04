<?php

declare(strict_types=1);

namespace Rentivo\Services;

use RuntimeException;

/**
 * Raised when an upload fails validation. The message is safe to show to the
 * user; it never contains a filesystem path.
 */
final class UploadException extends RuntimeException
{
}
