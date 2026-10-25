<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Machine;

final class VendingMachine
{
    private CoinBag $inserted;

    private function __construct(
        private readonly MachineId $machineId,
        private Inventory $inventory,
        private CoinBag $availableChange,
    ) {
        $this->inserted = CoinBag::empty();
    }

    public static function create(MachineId $machineId, Inventory $inventory, CoinBag $availableChange): self
    {
        return new self($machineId, $inventory, $availableChange);
    }

    public function id(): MachineId
    {
        return $this->machineId;
    }

    public function inventory(): Inventory
    {
        return $this->inventory;
    }

    public function availableChange(): CoinBag
    {
        return $this->availableChange;
    }

    public function insert(Coin $coin): void
    {
        $this->inserted = $this->inserted->add($coin);
    }

    public function inserted(): CoinBag
    {
        return $this->inserted;
    }

    public function returnCoins(): CoinBag
    {
        $coins = $this->inserted;
        $this->inserted = CoinBag::empty();
        return $coins;
    }

    public function purchase(Selector $selector, ChangeMaker $changeMaker): PurchaseResult
    {
        $slot = $this->inventory->get($selector);
        if (!$slot->isAvailable()) {
            throw OutOfStockException::forSelector($selector);
        }

        $insertedTotal = $this->inserted->total();
        if ($insertedTotal->isLessThan($slot->price())) {
            throw InsufficientFundsException::forPurchase($selector, $slot->price(), $insertedTotal);
        }

        $owed = $insertedTotal->subtract($slot->price());
        $heldCoins = $this->availableChange->merge($this->inserted);

        $change = $changeMaker->makeChange($owed, $heldCoins);
        if ($change === null) {
            throw CannotMakeChangeException::for($owed);
        }

        $inventory = $this->inventory->replace($slot->dispenseOne());
        $availableChange = $heldCoins->subtract($change);

        $this->inventory = $inventory;
        $this->availableChange = $availableChange;
        $this->inserted = CoinBag::empty();

        return PurchaseResult::create($selector, $change);
    }
}
