<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Machine;

use VendingMachine\Domain\Shared\Money;

enum Coin: int
{
    case Nickel = 5;
    case Dime = 10;
    case Quarter = 25;
    case Dollar = 100;

    public function toMoney(): Money
    {
        return Money::fromCents($this->value);
    }

    public static function fromMoney(Money $money): self
    {
        return self::tryFrom($money->cents()) ?? throw UnsupportedCoinException::forAmount($money);
    }

    public static function fromString(string $amount): self
    {
        return self::fromMoney(Money::fromString($amount));
    }

    public function isChangeable(): bool
    {
        if ($this === self::Dollar) {
            return false;
        }

        return true;
    }

    /**
     * @return list<self>
     */
    public static function changeable(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn(self $coin): bool => $coin->isChangeable(),
        ));
    }
}
