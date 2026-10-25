<?php

declare(strict_types=1);

namespace VendingMachine\Tests\Support;

use VendingMachine\Domain\Machine\MachineId;
use VendingMachine\Domain\Machine\MachineNotFoundException;
use VendingMachine\Domain\Machine\MachineRepository;
use VendingMachine\Domain\Machine\VendingMachine;

final class InMemoryMachineRepository implements MachineRepository
{
    /** @var array<string, string> serialized machines, keyed by machine id */
    private array $stored = [];

    public function get(MachineId $id): VendingMachine
    {
        $data = $this->stored[$id->value()] ?? throw MachineNotFoundException::withId($id);

        $machine = unserialize($data);
        assert($machine instanceof VendingMachine);
        return $machine;
    }

    public function save(VendingMachine $machine): void
    {
        $this->stored[$machine->id()->value()] = serialize($machine);
    }
}
