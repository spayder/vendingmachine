<?php

declare(strict_types=1);

namespace VendingMachine\Application\ReturnCoin;

use VendingMachine\Domain\Machine\CoinBag;
use VendingMachine\Domain\Machine\MachineId;
use VendingMachine\Domain\Machine\MachineRepository;

final readonly class ReturnCoinHandler
{
    public function __construct(private MachineRepository $repository) {}

    public function handle(ReturnCoinCommand $command): CoinBag
    {
        $machine = $this->repository->get(
            MachineId::fromString($command->machineId),
        );

        $returned = $machine->returnCoins();
        $this->repository->save($machine);
        return $returned;
    }
}
