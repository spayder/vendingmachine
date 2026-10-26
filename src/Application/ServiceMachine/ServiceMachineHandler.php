<?php

declare(strict_types=1);

namespace VendingMachine\Application\ServiceMachine;

use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Machine\CoinBag;
use VendingMachine\Domain\Machine\Inventory;
use VendingMachine\Domain\Machine\MachineId;
use VendingMachine\Domain\Machine\MachineRepository;
use VendingMachine\Domain\Machine\ProductSlot;
use VendingMachine\Domain\Machine\Selector;
use VendingMachine\Domain\Shared\Money;

final readonly class ServiceMachineHandler
{
    public function __construct(private MachineRepository $repository) {}

    public function handle(ServiceMachineCommand $command): void
    {
        $slots = [];
        foreach ($command->products as $product) {
            $slots[] = ProductSlot::create(
                Selector::fromString($product['selector']),
                Money::fromString($product['price']),
                $product['quantity'],
            );
        }

        $inventory = Inventory::of(...$slots);
        $coinBag = CoinBag::empty();
        foreach ($command->coins as $value => $quantity) {
            if ($quantity === 0) {
                continue;
            }

            $coinBag = $coinBag->add(Coin::fromString((string) $value), $quantity);
        }

        $machine = $this->repository->get(MachineId::fromString($command->machineId));
        $machine->service($inventory, $coinBag);

        $this->repository->save($machine);
    }
}
