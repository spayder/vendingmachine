<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Machine;

/**
 * What a successful purchase hands to the customer: the item and the change.
 */
final readonly class PurchaseResult
{
    public function __construct(
        private Selector $selector,
        private CoinBag $change,
    ) {}

    public static function create(Selector $selector, CoinBag $change): self
    {
        return new self($selector, $change);
    }

    public function selector(): Selector
    {
        return $this->selector;
    }

    public function change(): CoinBag
    {
        return $this->change;
    }
}
