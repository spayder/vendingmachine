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
