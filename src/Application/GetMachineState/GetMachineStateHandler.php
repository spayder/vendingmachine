<?php

declare(strict_types=1);

namespace VendingMachine\Application\GetMachineState;

use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Machine\CoinBag;
use VendingMachine\Domain\Machine\MachineId;
use VendingMachine\Domain\Machine\MachineRepository;

final readonly class GetMachineStateHandler
{
    public function __construct(private MachineRepository $repository) {}

    public function handle(GetMachineStateQuery $query): MachineState
    {
        $machine = $this->repository->get(MachineId::fromString($query->machineId));

        $products = [];
        foreach ($machine->inventory()->all() as $product) {
            $inventory = [
                'selector' => $product->selector()->value(),
                'price' => $product->price()->toString(),
                'quantity' => $product->quantity(),
            ];
            $products[] = $inventory;
        }

        return new MachineState(
            $products,
            $this->countsOf($machine->availableChange()),
            $this->countsOf($machine->inserted()),
            $machine->inserted()->total()->toString(),
        );
    }

    /** @return array<string, int> */
    private function countsOf(CoinBag $coinBag): array
    {
        $counts = [];
        foreach (Coin::cases() as $coin) {
            $counts[$coin->toMoney()->toString()] = $coinBag->quantityOf($coin);
        }

        return $counts;
    }
}
