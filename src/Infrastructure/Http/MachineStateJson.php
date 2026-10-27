<?php

declare(strict_types=1);

namespace VendingMachine\Infrastructure\Http;

use VendingMachine\Application\GetMachineState\MachineState;

final readonly class MachineStateJson
{
    /**
     * @return array{
     *     products: list<array{selector: string, price: string, quantity: int}>,
     *     availableChange: array<string, int>,
     *     inserted: array{coins: array<string, int>, total: string}
     * }
     */
    public static function of(MachineState $machineState): array
    {
        return [
            'products' => $machineState->products,
            'availableChange' => $machineState->availableChange,
            'inserted' => [
                'coins' => $machineState->insertedCoins,
                'total' => $machineState->insertedTotal,
            ],
        ];
    }
}
