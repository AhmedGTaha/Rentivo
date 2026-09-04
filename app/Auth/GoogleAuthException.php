<?php

declare(strict_types=1);

namespace Rentivo\Auth;

use RuntimeException;

/**
 * A Google sign-in failure. The message is user-safe and never contains
 * client secrets or raw token material.
 */
final class GoogleAuthException extends RuntimeException
{
}
