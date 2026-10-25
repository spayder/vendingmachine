<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Machine;

use VendingMachine\Domain\Shared\Money;

final class InsufficientFundsException extends \DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forPurchase(Selector $selector, Money $price, Money $inserted): self
    {
        return new self(sprintf(
            'Insufficient funds for %s: %s needed, %s inserted',
            $selector->value(),
            $price->toString(),
            $inserted->toString(),
        ));
    }
}
