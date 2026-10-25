<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Machine;

final class InvalidCoinQuantity extends \DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function fromQuantity(int $quantity): self
    {
        return new self("Coin quantity must be at least 1, got $quantity");
    }
}
