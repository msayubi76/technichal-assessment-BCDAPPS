<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business rule said no. These are expected situations (ride already
 * taken, no drivers nearby, and so on), so they are shown to the user
 * as-is and not reported as server errors.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
}
