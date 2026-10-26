<?php

declare(strict_types=1);

use VendingMachine\Domain\Machine\MachineRepository;
use VendingMachine\Infrastructure\Persistence\SqliteMachineRepository;
use VendingMachine\Tests\Support\InMemoryMachineRepository;

/*
 * Every MachineRepository implementation must pass the same contract tests.
 */
dataset('machine repositories', [
    'in memory' => [fn(): MachineRepository => new InMemoryMachineRepository()],
    'sqlite' => [fn(): MachineRepository => new SqliteMachineRepository(new PDO('sqlite::memory:'))],
]);
