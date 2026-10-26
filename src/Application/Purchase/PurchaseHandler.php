<?php

declare(strict_types=1);

namespace VendingMachine\Application\Purchase;

use VendingMachine\Domain\Machine\CannotMakeChangeException;
use VendingMachine\Domain\Machine\ChangeMaker;
use VendingMachine\Domain\Machine\MachineId;
use VendingMachine\Domain\Machine\MachineRepository;
use VendingMachine\Domain\Machine\PurchaseResult;
use VendingMachine\Domain\Machine\Selector;

final readonly class PurchaseHandler
{
    public function __construct(private MachineRepository $repository, private ChangeMaker $changeMaker) {}

    public function handle(PurchaseCommand $command): PurchaseResult
    {
        $selector = Selector::fromString($command->selector);
        $machine = $this->repository->get(MachineId::fromString($command->machineId));

        try {
            $result = $machine->purchase($selector, $this->changeMaker);
        } catch (CannotMakeChangeException $e) {
            $refund = $machine->returnCoins();
            $this->repository->save($machine);
            throw SaleRefundedException::because($e, $refund);
        }

        $this->repository->save($machine);
        return $result;
    }
}
