<?php

declare(strict_types=1);

/*
 * The layer rules from the README, enforced on every run: dependencies point inward only.
 *
 *   Infrastructure  ->  Application  ->  Domain
 */

arch('the domain depends on nothing outside itself')
    ->expect('VendingMachine\Domain')
    ->not->toUse([
        'VendingMachine\Application',
        'VendingMachine\Infrastructure',
        'Slim',
        'DI',
        'Psr',
        'Nyholm',
        'PDO',
    ]);

arch('the domain does no I/O')
    ->expect('VendingMachine\Domain')
    ->not->toUse(['file_get_contents', 'file_put_contents', 'fopen', 'curl_init', 'json_encode', 'json_decode', 'getenv']);

arch('the application layer knows nothing about HTTP, the database or the framework')
    ->expect('VendingMachine\Application')
    ->not->toUse([
        'VendingMachine\Infrastructure',
        'Slim',
        'DI',
        'Psr',
        'Nyholm',
        'PDO',
    ]);

arch('commands, queries and read models are immutable')
    ->expect('VendingMachine\Application')
    ->classes
    ->toBeReadonly()
    ->ignoring(['VendingMachine\Application\Purchase\SaleRefundedException']);

arch('the repository port is an interface that lives in the domain')
    ->expect('VendingMachine\Domain\Machine\MachineRepository')
    ->toBeInterface();

arch('the change maker strategy is an interface that lives in the domain')
    ->expect('VendingMachine\Domain\Machine\ChangeMaker')
    ->toBeInterface();

arch('the SQLite repository implements the port')
    ->expect('VendingMachine\Infrastructure\Persistence\SqliteMachineRepository')
    ->toImplement('VendingMachine\Domain\Machine\MachineRepository');

arch('exceptions are named as exceptions')
    ->expect('VendingMachine')
    ->extending(Throwable::class)
    ->toHaveSuffix('Exception');

arch('exceptions are final')
    ->expect('VendingMachine')
    ->extending(Throwable::class)
    ->toBeFinal();

arch('domain exceptions are domain exceptions')
    ->expect('VendingMachine\Domain')
    ->extending(Throwable::class)
    ->toExtend(DomainException::class);

arch('every class is final unless it is meant to be extended')
    ->expect('VendingMachine')
    ->classes
    ->toBeFinal();

arch('all code uses strict types')
    ->expect('VendingMachine')
    ->toUseStrictTypes();

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();
