<?php

declare(strict_types=1);

namespace VendingMachine\Application\GetMachineState;

final readonly class GetMachineStateQuery
{
    public function __construct(public string $machineId) {}
}
