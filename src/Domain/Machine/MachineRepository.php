<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Machine;

interface MachineRepository
{
    /**
     * @throws MachineNotFoundException when no machine has that id
     */
    public function get(MachineId $id): VendingMachine;

    /**
     * Stores the machine, creating it when it is new and replacing the stored one otherwise.
     */
    public function save(VendingMachine $machine): void;
}
