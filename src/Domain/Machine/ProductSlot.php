<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Machine;

use VendingMachine\Domain\Shared\Money;

final readonly class ProductSlot
{
    private function __construct(private Selector $selector, private Money $price, private int $quantity)
    {
        if ($quantity < 0) {
            throw InvalidStock::for($quantity);
        }
    }

    public static function create(Selector $selector, Money $price, int $quantity): self
    {
        return new self($selector, $price, $quantity);
    }

    public function selector(): Selector
    {
        return $this->selector;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function isAvailable(): bool
    {
        return $this->quantity > 0;
    }

    public function dispenseOne(): self
    {
        if (!$this->isAvailable()) {
            throw OutOfStock::forSelector($this->selector);
        }

        return new self(
            $this->selector,
            $this->price,
            $this->quantity - 1,
        );
    }
}
