<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Machine;

use VendingMachine\Domain\Shared\Money;

final class SessionInProgressException extends \DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function withInserted(Money $inserted): self
    {
        return new self('Machine is in use: ' . $inserted->toString() . ' inserted');
    }
}
