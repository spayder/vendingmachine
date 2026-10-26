<?php

declare(strict_types=1);

use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Machine\CoinBag;
use VendingMachine\Domain\Machine\MachineId;
use VendingMachine\Infrastructure\Persistence\CorruptMachineStateException;
use VendingMachine\Infrastructure\Persistence\SqliteMachineRepository;

/**
 * What every repository must do is in tests/Contract. These tests cover what only a database can get wrong.
 */
function withDatabaseFile(Closure $scenario): void
{
    $path = tempnam(sys_get_temp_dir(), 'machines');
    assert(is_string($path));

    try {
        $scenario($path);
    } finally {
        unlink($path);
    }
}

describe('SqliteMachineRepository', function () {
    it('keeps machines between connections, as a restart of the app would', function () {
        withDatabaseFile(function (string $path) {
            $machine = machine(CoinBag::empty()->add(Coin::Dime, 3));
            $machine->insert(Coin::Quarter);
            new SqliteMachineRepository(new PDO("sqlite:$path"))->save($machine);

            $loaded = new SqliteMachineRepository(new PDO("sqlite:$path"))->get(MachineId::fromString('default'));

            expect($loaded->availableChange())->toEqual(CoinBag::empty()->add(Coin::Dime, 3))
                ->and($loaded->inserted())->toEqual(CoinBag::of(Coin::Quarter))
                ->and($loaded->inventory())->toEqual(stockedInventory());
        });
    });

    it('can be created again on a database that already has its table', function () {
        $pdo = new PDO('sqlite::memory:');
        new SqliteMachineRepository($pdo)->save(machine());

        $repository = new SqliteMachineRepository($pdo);

        expect($repository->get(MachineId::fromString('default'))->id()->value())->toBe('default');
    });

    it('stores one row per machine, however often it is saved', function () {
        $pdo = new PDO('sqlite::memory:');
        $repository = new SqliteMachineRepository($pdo);

        $repository->save(machine());
        $repository->save(machine());
        $repository->save(machineWithId('lobby'));

        $rows = $pdo->query('SELECT COUNT(*) FROM machines');
        assert($rows instanceof PDOStatement);

        expect($rows->fetchColumn())->toBe(2);
    });

    describe('when the stored state is damaged', function () {
        it('fails loudly instead of building a half-broken machine', function (string $state) {
            $pdo = new PDO('sqlite::memory:');
            $repository = new SqliteMachineRepository($pdo);
            $pdo->prepare('INSERT INTO machines (id, state) VALUES (?, ?)')->execute(['broken', $state]);

            expect(fn() => $repository->get(MachineId::fromString('broken')))
                ->toThrow(CorruptMachineStateException::class);
        })->with([
            'not json' => ['{oops'],
            'json but not an object' => ['42'],
            'nothing in it' => ['{}'],
            'coin that does not exist' => ['{"products":[],"availableChange":{"7":1},"inserted":{}}'],
            'negative stock' => ['{"products":[{"selector":"WATER","price":65,"quantity":-1}],"availableChange":{},"inserted":{}}'],
        ]);
    });
});
