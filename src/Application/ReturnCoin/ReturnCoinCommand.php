<?php

declare(strict_types=1);

namespace VendingMachine\Application\ReturnCoin;

final readonly class ReturnCoinCommand
{
    public function __construct(
        public string $machineId,
    ) {}
}
