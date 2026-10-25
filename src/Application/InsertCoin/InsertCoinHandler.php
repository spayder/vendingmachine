<?php

declare(strict_types=1);

namespace VendingMachine\Application\InsertCoin;

use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Machine\CoinBag;
use VendingMachine\Domain\Machine\MachineId;
use VendingMachine\Domain\Machine\MachineRepository;

final readonly class InsertCoinHandler
{
    public function __construct(private MachineRepository $repository) {}

    public function handle(InsertCoinCommand $command): CoinBag
    {
        $coin = Coin::fromString($command->coin);
        $machine = $this->repository->get(
            MachineId::fromString($command->machineId),
        );

        $machine->insert($coin);

        $this->repository->save($machine);
        return $machine->inserted();
    }
}
