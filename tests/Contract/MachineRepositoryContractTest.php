<?php

declare(strict_types=1);

use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Machine\CoinBag;
use VendingMachine\Domain\Machine\Inventory;
use VendingMachine\Domain\Machine\MachineId;
use VendingMachine\Domain\Machine\MachineNotFoundException;
use VendingMachine\Domain\Machine\Selector;
use VendingMachine\Domain\Machine\VendingMachine;

describe('get', function () {
    it('rejects an id nobody saved', function (Closure $factory) {
        $repository = repositoryFrom($factory);

        expect(fn() => $repository->get(MachineId::fromString('nope')))
            ->toThrow(MachineNotFoundException::class, 'Machine not found: nope');
    })->with('machine repositories');

    it('returns what was saved, session included', function (Closure $factory) {
        $repository = repositoryFrom($factory);
        $machine = VendingMachine::create(
            MachineId::fromString('lobby'),
            Inventory::of(slotOf('tea', 90, 4)),
            CoinBag::empty()->add(Coin::Dime, 3),
        );
        $machine->insert(Coin::Quarter);
        $repository->save($machine);

        $loaded = $repository->get(MachineId::fromString('lobby'));

        expect($loaded->id()->value())->toBe('lobby')
            ->and($loaded->inventory()->get(Selector::fromString('tea'))->quantity())->toBe(4)
            ->and($loaded->inventory()->get(Selector::fromString('tea'))->price()->cents())->toBe(90)
            ->and($loaded->availableChange())->toEqual(CoinBag::empty()->add(Coin::Dime, 3))
            ->and($loaded->inserted())->toEqual(CoinBag::of(Coin::Quarter));
    })->with('machine repositories');

    it('keeps machines with different ids apart', function (Closure $factory) {
        $repository = repositoryFrom($factory);
        $first = machineWithId('first');
        $first->insert(Coin::Dollar);
        $repository->save($first);
        $repository->save(machineWithId('second'));

        expect($repository->get(MachineId::fromString('first'))->inserted())->toEqual(CoinBag::of(Coin::Dollar))
            ->and($repository->get(MachineId::fromString('second'))->inserted()->isEmpty())->toBeTrue();
    })->with('machine repositories');

    it('does not see changes that were never saved', function (Closure $factory) {
        $repository = repositoryFrom($factory);
        $repository->save(machineWithId('default'));

        $loaded = $repository->get(MachineId::fromString('default'));
        $loaded->insert(Coin::Quarter);

        expect($repository->get(MachineId::fromString('default'))->inserted()->isEmpty())->toBeTrue();
    })->with('machine repositories');
});

describe('save', function () {
    it('stores later changes to a machine it handed out', function (Closure $factory) {
        $repository = repositoryFrom($factory);
        $repository->save(machineWithId('default'));

        $machine = $repository->get(MachineId::fromString('default'));
        $machine->insert(Coin::Quarter);
        $repository->save($machine);

        expect($repository->get(MachineId::fromString('default'))->inserted())->toEqual(CoinBag::of(Coin::Quarter));
    })->with('machine repositories');

    it('lets the same instance be saved again and again', function (Closure $factory) {
        $repository = repositoryFrom($factory);
        $machine = machineWithId('default');
        $repository->save($machine);

        $machine->insert(Coin::Dime);
        $repository->save($machine);
        $machine->insert(Coin::Dime);
        $repository->save($machine);

        expect($repository->get(MachineId::fromString('default'))->inserted())->toEqual(CoinBag::of(Coin::Dime, Coin::Dime));
    })->with('machine repositories');
});
