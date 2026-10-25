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
}
