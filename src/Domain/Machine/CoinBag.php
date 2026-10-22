<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Machine;

use VendingMachine\Domain\Shared\Money;

final readonly class CoinBag
{
    /** @param array<int, int> $counts coin value in cents => quantity */
    private function __construct(private array $counts) {}

    public static function empty(): self
    {
        return new self([]);
    }

    public function isEmpty(): bool
    {
        return $this->counts === [];
    }

    public function quantityOf(Coin $coin): int
    {
        return $this->counts[$coin->value] ?? 0;
    }

    public function total(): Money
    {
        // counts stores coin|quantity like: [25 => 2, 10 => 1]
        $cents = 0;
        foreach ($this->counts as $value => $quantity) {
            $cents += $value * $quantity;
        }

        return Money::fromCents($cents);
    }

    public static function of(Coin ...$coins): self
    {
        $counts = [];
        foreach ($coins as $coin) {
            $counts[$coin->value] = ($counts[$coin->value] ?? 0) + 1;
        }

        return new self($counts);
    }

    public function add(Coin $coin, int $quantity = 1): self
    {
        $counts = $this->counts;
        $counts[$coin->value] = ($counts[$coin->value] ?? 0) + $quantity;

        return new self($counts);
    }
}
