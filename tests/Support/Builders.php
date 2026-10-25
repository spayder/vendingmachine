<?php

declare(strict_types=1);


use VendingMachine\Domain\Machine\CoinBag;
use VendingMachine\Domain\Machine\Inventory;
use VendingMachine\Domain\Machine\MachineId;
use VendingMachine\Domain\Machine\ProductSlot;
use VendingMachine\Domain\Machine\Selector;
use VendingMachine\Domain\Machine\VendingMachine;
use VendingMachine\Domain\Shared\Money;

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
