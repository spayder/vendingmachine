<?php

declare(strict_types=1);

namespace VendingMachine\Infrastructure\Http;

/**
 * The request cannot be understood at all: not JSON, not an object, or a field of the wrong type.
 */
final class MalformedRequestException extends \RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function because(string $reason): self
    {
        return new self('Malformed request: ' . $reason);
    }
}
