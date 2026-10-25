<?php

declare(strict_types=1);

use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Machine\CoinBag;
use VendingMachine\Domain\Machine\MachineId;
use VendingMachine\Domain\Machine\VendingMachine;

describe('create', function () {
    it('starts with the id, inventory and available change it was given and nothing inserted', function () {
        $inventory = stockedInventory();
        $availableChange = CoinBag::empty()->add(Coin::Quarter, 4);

        $machine = VendingMachine::create(MachineId::fromString('lobby'), $inventory, $availableChange);

        expect($machine->id()->value())->toBe('lobby')
            ->and($machine->inventory())->toBe($inventory)
            ->and($machine->availableChange())->toEqual($availableChange)
            ->and($machine->inserted()->isEmpty())->toBeTrue();
    });
});

describe('insert', function () {
    it('keeps the coins the customer inserted', function () {
        $machine = machine();

        $machine->insert(Coin::Dollar);
        $machine->insert(Coin::Quarter);
        $machine->insert(Coin::Quarter);

        expect($machine->inserted())->toEqual(CoinBag::of(Coin::Dollar, Coin::Quarter, Coin::Quarter))
            ->and($machine->inserted()->total()->cents())->toBe(150);
    });

    it('keeps inserted coins apart from the available change', function () {
        $machine = machine(CoinBag::of(Coin::Dime));

        $machine->insert(Coin::Quarter);

        expect($machine->availableChange())->toEqual(CoinBag::of(Coin::Dime))
            ->and($machine->inserted())->toEqual(CoinBag::of(Coin::Quarter));
    });
});

describe('returnCoins', function () {
    it('gives back exactly the coins inserted, dollar included (spec example 2)', function () {
        $machine = machine();
        $machine->insert(Coin::Dime);
        $machine->insert(Coin::Dime);

        $returned = $machine->returnCoins();

        expect($returned)->toEqual(CoinBag::of(Coin::Dime, Coin::Dime));
    });

    it('gives back a dollar as a dollar', function () {
        $machine = machine();
        $machine->insert(Coin::Dollar);

        expect($machine->returnCoins())->toEqual(CoinBag::of(Coin::Dollar));
    });

    it('empties the inserted coins', function () {
        $machine = machine();
        $machine->insert(Coin::Quarter);

        $machine->returnCoins();

        expect($machine->inserted()->isEmpty())->toBeTrue();
    });

    it('gives back nothing when nothing was inserted', function () {
        expect(machine()->returnCoins()->isEmpty())->toBeTrue();
    });

    it('does not touch the available change', function () {
        $availableChange = CoinBag::empty()->add(Coin::Nickel, 3);
        $machine = machine($availableChange);
        $machine->insert(Coin::Quarter);

        $machine->returnCoins();

        expect($machine->availableChange())->toEqual($availableChange);
    });
});
