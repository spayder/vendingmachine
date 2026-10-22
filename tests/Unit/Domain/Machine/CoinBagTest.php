<?php

declare(strict_types=1);

use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Machine\CoinBag;

describe('empty', function () {
    it('holds no coins', function () {
        $bag = CoinBag::empty();

        expect($bag->isEmpty())->toBeTrue()
            ->and($bag->total()->cents())->toBe(0);
    });

    it('counts zero of every coin', function (Coin $coin) {
        expect(CoinBag::empty()->quantityOf($coin))->toBe(0);
    })->with(Coin::cases());
});

describe('of', function () {
    it('counts how many times each coin appears', function () {
        $bag = CoinBag::of(Coin::Quarter, Coin::Dime, Coin::Quarter);

        expect($bag->quantityOf(Coin::Quarter))->toBe(2)
            ->and($bag->quantityOf(Coin::Dime))->toBe(1)
            ->and($bag->quantityOf(Coin::Nickel))->toBe(0)
            ->and($bag->quantityOf(Coin::Dollar))->toBe(0);
    });

    it('is not empty when it holds coins', function () {
        expect(CoinBag::of(Coin::Nickel)->isEmpty())->toBeFalse();
    });

    it('is empty when given no coins', function () {
        expect(CoinBag::of()->isEmpty())->toBeTrue();
    });
});

describe('total', function () {
    it('sums the value of all coins', function (array $coins, int $expectedCents) {
        /** @var list<Coin> $coins */
        expect(CoinBag::of(...$coins)->total()->cents())->toBe($expectedCents);
    })->with([
        'single nickel' => [[Coin::Nickel], 5],
        'dollar and two quarters' => [[Coin::Dollar, Coin::Quarter, Coin::Quarter], 150],
        'one of each' => [[Coin::Nickel, Coin::Dime, Coin::Quarter, Coin::Dollar], 140],
        'many dimes' => [array_fill(0, 7, Coin::Dime), 70],
    ]);
});

describe('add', function () {
    it('adds one coin by default', function () {
        $bag = CoinBag::empty()->add(Coin::Quarter);

        expect($bag->quantityOf(Coin::Quarter))->toBe(1)
            ->and($bag->total()->cents())->toBe(25);
    });

    it('adds several coins at once', function () {
        $bag = CoinBag::of(Coin::Dime)->add(Coin::Dime, 3);

        expect($bag->quantityOf(Coin::Dime))->toBe(4)
            ->and($bag->total()->cents())->toBe(40);
    });

    it('does not modify the original bag', function () {
        $original = CoinBag::of(Coin::Nickel);

        $original->add(Coin::Nickel, 2);

        expect($original->quantityOf(Coin::Nickel))->toBe(1);
    });
});
