<?php

declare(strict_types=1);

namespace VendingMachine\Application\Purchase;

final readonly class PurchaseCommand
{
    public function __construct(
        public string $machineId,
        public string $selector,
    ) {}
}
