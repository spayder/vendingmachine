<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Machine;

use VendingMachine\Domain\Shared\Money;

interface ChangeMaker
{
    public function makeChange(Money $money, CoinBag $coinBag): ?CoinBag;
}
