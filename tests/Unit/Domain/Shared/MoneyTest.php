<?php

declare(strict_types=1);

use VendingMachine\Domain\Shared\InvalidMoney;
use VendingMachine\Domain\Shared\Money;

describe('fromCents', function () {
    it('exposes the amount it was created with', function (int $cents) {
        expect(Money::fromCents($cents)->cents())->toBe($cents);
    })->with([0, 5, 65, 100, 150]);

    it('rejects negative amounts', function (int $cents) {
        expect(fn() => Money::fromCents($cents))->toThrow(InvalidMoney::class, "Money cannot be negative, got {$cents} cents");
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

describe('add', function () {
    it('sums two amounts', function (int $left, int $right, int $expected) {
        $sum = Money::fromCents($left)->add(Money::fromCents($right));

        expect($sum->cents())->toBe($expected);
    })->with([
        'zero plus zero' => [0, 0, 0],
        'zero is neutral' => [65, 0, 65],
        'dime plus nickel' => [10, 5, 15],
        'two quarters and a dollar' => [50, 100, 150],
    ]);

    it('does not modify the operands', function () {
        $left = Money::fromCents(10);
        $right = Money::fromCents(5);

        $left->add($right);

        expect($left->cents())->toBe(10)
            ->and($right->cents())->toBe(5);
    });

    it('rejects a sum that overflows the integer range', function () {
        expect(fn() => Money::fromCents(PHP_INT_MAX)->add(Money::fromCents(1)))
            ->toThrow(InvalidMoney::class, 'Money overflow');
    });

    it('accepts a sum that lands exactly on the maximum', function () {
        $sum = Money::fromCents(PHP_INT_MAX - 1)->add(Money::fromCents(1));

        expect($sum->cents())->toBe(PHP_INT_MAX);
    });
});

describe('subtract', function () {
    it('subtracts one amount from another', function (int $left, int $right, int $expected) {
        $difference = Money::fromCents($left)->subtract(Money::fromCents($right));

        expect($difference->cents())->toBe($expected);
    })->with([
        'same amount' => [65, 65, 0],
        'zero is neutral' => [65, 0, 65],
        'change from a dollar' => [100, 65, 35],
        'change from a dollar for soda' => [150, 100, 50],
    ]);

    it('does not modify the operands', function () {
        $left = Money::fromCents(100);
        $right = Money::fromCents(65);

        $left->subtract($right);

        expect($left->cents())->toBe(100)
            ->and($right->cents())->toBe(65);
    });

    it('rejects a result below zero', function () {
        expect(fn() => Money::fromCents(5)->subtract(Money::fromCents(10)))
            ->toThrow(InvalidMoney::class, 'Money cannot be negative, got -5 cents');
    });
});
