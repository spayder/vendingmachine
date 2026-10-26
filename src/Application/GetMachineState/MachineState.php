<?php

declare(strict_types=1);

namespace VendingMachine\Application\GetMachineState;

final readonly class MachineState
{
    /**
     * @param list<array{selector: string, price: string, quantity: int}> $products
     * @param array<string, int> $availableChange  coin value => count, every coin present
     * @param array<string, int> $insertedCoins
     */
    public function __construct(
        public array $products,
        public array $availableChange,
        public array $insertedCoins,
        public string $insertedTotal,
    ) {}
}
