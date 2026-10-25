<?php

declare(strict_types=1);

use VendingMachine\Domain\Machine\InvalidStockException;
use VendingMachine\Domain\Machine\OutOfStockException;
use VendingMachine\Domain\Machine\ProductSlot;
use VendingMachine\Domain\Machine\Selector;
use VendingMachine\Domain\Shared\Money;

function waterSlot(int $quantity = 5): ProductSlot
{
    return ProductSlot::create(Selector::fromString('water'), Money::fromCents(65), $quantity);
}

describe('create', function () {
    it('exposes what it was created with', function () {
        $slot = waterSlot(8);

        expect($slot->selector()->value())->toBe('WATER')
            ->and($slot->price()->cents())->toBe(65)
            ->and($slot->quantity())->toBe(8);
    });

    it('accepts an empty slot', function () {
        expect(waterSlot(0)->quantity())->toBe(0);
    });

    it('rejects a negative quantity', function (int $quantity) {
        expect(fn() => waterSlot($quantity))
            ->toThrow(InvalidStockException::class, "Stock cannot be negative, got {$quantity}");
    })->with([-1, -10]);
});

describe('isAvailable', function () {
    it('is true while items are left', function (int $quantity) {
        expect(waterSlot($quantity)->isAvailable())->toBeTrue();
    })->with([1, 2, 50]);

    it('is false when sold out', function () {
        expect(waterSlot(0)->isAvailable())->toBeFalse();
    });
});

describe('dispenseOne', function () {
    it('returns a slot with one item fewer', function () {
        $after = waterSlot()->dispenseOne();

        expect($after->quantity())->toBe(4);
    });

    it('keeps the selector and the price', function () {
        $after = waterSlot()->dispenseOne();

        expect($after->selector()->equals(Selector::fromString('water')))->toBeTrue()
            ->and($after->price()->cents())->toBe(65);
    });

    it('does not modify the original slot', function () {
        $original = waterSlot(5);

        $original->dispenseOne();

        expect($original->quantity())->toBe(5);
    });

    it('sells the last item and becomes unavailable', function () {
        $after = waterSlot(1)->dispenseOne();

        expect($after->quantity())->toBe(0)
            ->and($after->isAvailable())->toBeFalse();
    });

    it('refuses to dispense from an empty slot', function () {
        expect(fn() => waterSlot(0)->dispenseOne())
            ->toThrow(OutOfStockException::class, 'Out of stock: WATER');
    });
});
