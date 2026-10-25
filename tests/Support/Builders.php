<?php

declare(strict_types=1);

use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Machine\CoinBag;
use VendingMachine\Domain\Machine\Inventory;
use VendingMachine\Domain\Machine\MachineId;
use VendingMachine\Domain\Machine\MachineRepository;
use VendingMachine\Domain\Machine\ProductSlot;
use VendingMachine\Domain\Machine\Selector;
use VendingMachine\Domain\Machine\VendingMachine;
use VendingMachine\Domain\Shared\Money;
use VendingMachine\Tests\Support\InMemoryMachineRepository;

function slotOf(string $selector, int $priceCents, int $quantity): ProductSlot
{
    return ProductSlot::create(Selector::fromString($selector), Money::fromCents($priceCents), $quantity);
}

/**
 * The machine from the spec: Water 0.65, Juice 1.00, Soda 1.50, ten of each.
 */
function stockedInventory(): Inventory
{
    return Inventory::of(
        slotOf('water', 65, 10),
        slotOf('juice', 100, 10),
        slotOf('soda', 150, 10),
    );
}

function machine(?CoinBag $availableChange = null, ?Inventory $inventory = null): VendingMachine
{
    return VendingMachine::create(
        MachineId::fromString('default'),
        $inventory ?? stockedInventory(),
        $availableChange ?? CoinBag::empty(),
    );
}

/**
 * Inserts the coins in order and hands the machine back, so a scenario reads in one line.
 */
function insertCoins(VendingMachine $machine, Coin ...$coins): VendingMachine
{
    foreach ($coins as $coin) {
        $machine->insert($coin);
    }

    return $machine;
}

/**
 * Runs a repository factory taken from the 'machine repositories' dataset.
 */
function repositoryFrom(Closure $factory): MachineRepository
{
    $repository = $factory();
    assert($repository instanceof MachineRepository);

    return $repository;
}

function machineWithId(string $id): VendingMachine
{
    return VendingMachine::create(MachineId::fromString($id), stockedInventory(), CoinBag::empty());
}

/**
 * An in-memory repository already holding the stocked machine 'default'.
 */
function repositoryWithDefaultMachine(): InMemoryMachineRepository
{
    $repository = new InMemoryMachineRepository();
    $repository->save(machineWithId('default'));

    return $repository;
}
