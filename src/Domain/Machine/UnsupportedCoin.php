<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Machine;

use VendingMachine\Domain\Shared\Money;

final class UnsupportedCoin extends \DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forAmount(Money $money): self
    {
        return new self("Unsupported coin amount: {$money->cents()} cents");
    }
}
