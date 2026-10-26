<?php

declare(strict_types=1);

namespace VendingMachine\Application\ServiceMachine;

final readonly class ServiceMachineCommand
{
    /**
     * @param list<array{selector: string, price: string, quantity: int}> $products
     * @param array<int|string, int> $coins  coin value => count, e.g. ['0.25' => 4, '0.10' => 10]
     */
    public function __construct(
        public string $machineId,
        public array $products,
        public array $coins,
    ) {}
}
