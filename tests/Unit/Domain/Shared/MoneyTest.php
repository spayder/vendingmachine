<?php

declare(strict_types=1);

use VendingMachine\Domain\Shared\InvalidMoney;
use VendingMachine\Domain\Shared\Money;

describe('fromCents', function () {
    it('exposes the amount it was created with', function (int $cents) {
        expect(Money::fromCents($cents)->cents())->toBe($cents);
    })->with([0, 5, 65, 100, 150]);

    it('rejects negative amounts', function (int $cents) {
        expect(fn() => Money::fromCents($cents))->toThrow(InvalidMoney::class, 'Money cannot be negative');
    })->with([-1, -5, -100, PHP_INT_MIN]);
});

describe('zero', function () {
    it('has no cents', function () {
        expect(Money::zero()->cents())->toBe(0);
    });

    it('is zero', function () {
        expect(Money::zero()->isZero())->toBeTrue();
    });
});

describe('isZero', function () {
    it('is true only for an amount of zero cents', function (int $cents, bool $expected) {
        expect(Money::fromCents($cents)->isZero())->toBe($expected);
    })->with([
        'zero' => [0, true],
        'one cent' => [1, false],
        'a nickel' => [5, false],
        'a dollar' => [100, false],
    ]);
});

describe('toString', function () {
    it('formats the amount with two decimals', function (int $cents, string $expected) {
        expect(Money::fromCents($cents)->toString())->toBe($expected);
    })->with([
        'zero' => [0, '0.00'],
        'one cent' => [1, '0.01'],
        'a nickel' => [5, '0.05'],
        'a dime' => [10, '0.10'],
        'a quarter' => [25, '0.25'],
        'water price' => [65, '0.65'],
        'a dollar' => [100, '1.00'],
        'soda price' => [150, '1.50'],
        'large amount' => [123456, '1234.56'],
    ]);
});
