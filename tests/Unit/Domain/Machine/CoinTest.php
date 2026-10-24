<?php

declare(strict_types=1);

use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Shared\InvalidMoney;
use VendingMachine\Domain\Shared\Money;
use VendingMachine\Domain\Machine\UnsupportedCoin;

describe('toMoney', function () {
    it('is worth its denomination', function (Coin $coin, int $expectedCents) {
        expect($coin->toMoney()->cents())->toBe($expectedCents);
    })->with([
        'nickel' => [Coin::Nickel, 5],
        'dime' => [Coin::Dime, 10],
        'quarter' => [Coin::Quarter, 25],
        'dollar' => [Coin::Dollar, 100],
    ]);
});

describe('isChangeable', function () {
    it('tells whether the machine can hand the coin back as change', function (Coin $coin, bool $expected) {
        expect($coin->isChangeable())->toBe($expected);
    })->with([
        'nickel' => [Coin::Nickel, true],
        'dime' => [Coin::Dime, true],
        'quarter' => [Coin::Quarter, true],
        'dollar' => [Coin::Dollar, false],
    ]);
});

describe('fromMoney', function () {
    it('finds the coin with that value', function (int $cents, Coin $expected) {
        expect(Coin::fromMoney(Money::fromCents($cents)))->toBe($expected);
    })->with([
        'nickel' => [5, Coin::Nickel],
        'dime' => [10, Coin::Dime],
        'quarter' => [25, Coin::Quarter],
        'dollar' => [100, Coin::Dollar],
    ]);

    it('rejects an amount no coin is worth', function (int $cents) {
        expect(fn() => Coin::fromMoney(Money::fromCents($cents)))
            ->toThrow(UnsupportedCoin::class, 'Unsupported coin');
    })->with([0, 1, 15, 50, 200]);
});

describe('fromString', function () {
    it('parses the amounts the machine accepts', function (string $amount, Coin $expected) {
        expect(Coin::fromString($amount))->toBe($expected);
    })->with([
        '0.05' => ['0.05', Coin::Nickel],
        '0.10' => ['0.10', Coin::Dime],
        '0.1 is a dime' => ['0.1', Coin::Dime],
        '0.25' => ['0.25', Coin::Quarter],
        '1' => ['1', Coin::Dollar],
        '1.00' => ['1.00', Coin::Dollar],
    ]);

    it('rejects a valid amount that is not a coin', function (string $amount) {
        expect(fn() => Coin::fromString($amount))->toThrow(UnsupportedCoin::class);
    })->with(['0.50', '2', '0', '0.01']);

    it('rejects text that is not an amount', function () {
        expect(fn() => Coin::fromString('quarter'))->toThrow(InvalidMoney::class);
    });
});

describe('changeable', function () {
    it('lists the coins the machine can hand back', function () {
        expect(Coin::changeable())->toBe([Coin::Nickel, Coin::Dime, Coin::Quarter]);
    });
});
