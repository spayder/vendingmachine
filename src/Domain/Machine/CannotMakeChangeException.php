<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Machine;

use VendingMachine\Domain\Shared\Money;

final class CannotMakeChangeException extends \DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function for(Money $owed): self
    {
        return new self('Cannot make change: ' . $owed->toString() . ' owed');
    }
}
