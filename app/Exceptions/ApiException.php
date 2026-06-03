<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ApiException extends Exception
{
    /**
     * @param  array<string, mixed>|null  $details
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly ?array $details = null,
    ) {
        parent::__construct($message);
    }
}
