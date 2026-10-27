<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Machine;

use VendingMachine\Domain\Shared\Money;

final class UnsupportedCoinException extends \DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forAmount(Money $money): self
    {
        $accepted = implode(', ', array_map(
            static fn(Coin $coin): string => $coin->toMoney()->toString(),
            Coin::cases(),
        ));

        return new self("Unsupported coin: {$money->toString()} (accepted: $accepted)");
    }
}
