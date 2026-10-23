<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Machine;

class InsufficientCoins extends \Exception
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function for(Coin $coin, int $quantity, int $available): self
    {
        return new self("Cannot remove $quantity x $coin->value, only $available available");
    }
}
