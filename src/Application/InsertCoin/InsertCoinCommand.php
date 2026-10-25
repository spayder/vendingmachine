<?php

declare(strict_types=1);

namespace VendingMachine\Application\InsertCoin;

/**
 * Raw input of the use case: strings straight from the outside world.
 * The handler turns them into domain objects, so every validation error comes from the domain.
 */
final readonly class InsertCoinCommand
{
    public function __construct(
        public string $machineId,
        public string $coin,
    ) {}
}
