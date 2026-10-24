<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Machine;

final class InvalidStock extends \DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function for(int $quantity): self
    {
        return new self("Stock cannot be negative, got $quantity");
    }
}
