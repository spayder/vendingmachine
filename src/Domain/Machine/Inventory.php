<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Machine;

final readonly class Inventory
{
    /** @param array<string, ProductSlot> $slots */
    private function __construct(private array $slots) {}

    public static function empty(): self
    {
        return new self([]);
    }

    public static function of(ProductSlot ...$productSlots): self
    {
        $slots = [];

        foreach ($productSlots as $slot) {
            $key = $slot->selector()->value();

            if (isset($slots[$key])) {
                throw DuplicateProductException::forSelector($slot->selector());
            }

            $slots[$key] = $slot;
        }

        return new self($slots);
    }

    public function replace(ProductSlot $slot): self
    {
        $slots = $this->slots;
        $selectorValue = $slot->selector()->value();

        if (!array_key_exists($selectorValue, $slots)) {
            throw UnknownProductException::forSelector($slot->selector());
        }

        $slots[$selectorValue] = $slot;

        return new self($slots);
    }

    public function has(Selector $selector): bool
    {
        return isset($this->slots[$selector->value()]);
    }

    /** @return list<ProductSlot> */
    public function all(): array
    {
        return array_values($this->slots);
    }

    public function get(Selector $selector): ProductSlot
    {
        if (!$this->has($selector)) {
            throw UnknownProductException::forSelector($selector);
        }

        return $this->slots[$selector->value()];
    }
}
