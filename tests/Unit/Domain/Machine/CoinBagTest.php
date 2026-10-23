<?php

declare(strict_types=1);

use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Machine\CoinBag;
use VendingMachine\Domain\Machine\InsufficientCoins;
use VendingMachine\Domain\Machine\InvalidCoinQuantity;

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

    it('rejects a quantity below one', function (int $quantity) {
        expect(fn() => CoinBag::empty()->add(Coin::Dime, $quantity))
            ->toThrow(InvalidCoinQuantity::class, "Coin quantity must be at least 1, got {$quantity}");
    })->with([0, -1, -10]);
});

describe('merge', function () {
    it('combines the coins of both bags', function () {
        $merged = CoinBag::of(Coin::Quarter, Coin::Dime)
            ->merge(CoinBag::of(Coin::Quarter, Coin::Nickel));

        expect($merged->quantityOf(Coin::Quarter))->toBe(2)
            ->and($merged->quantityOf(Coin::Dime))->toBe(1)
            ->and($merged->quantityOf(Coin::Nickel))->toBe(1)
            ->and($merged->total()->cents())->toBe(65);
    });

    it('treats an empty bag as neutral', function () {
        $bag = CoinBag::of(Coin::Dollar, Coin::Dime);

        expect($bag->merge(CoinBag::empty()))->toEqual($bag)
            ->and(CoinBag::empty()->merge($bag))->toEqual($bag);
    });

    it('does not modify either operand', function () {
        $left = CoinBag::of(Coin::Dime);
        $right = CoinBag::of(Coin::Dime);

        $left->merge($right);

        expect($left->quantityOf(Coin::Dime))->toBe(1)
            ->and($right->quantityOf(Coin::Dime))->toBe(1);
    });
});

describe('subtract', function () {
    it('removes the given coins', function () {
        $remaining = CoinBag::of(Coin::Quarter, Coin::Quarter, Coin::Dime)
            ->subtract(CoinBag::of(Coin::Quarter, Coin::Dime));

        expect($remaining->quantityOf(Coin::Quarter))->toBe(1)
            ->and($remaining->quantityOf(Coin::Dime))->toBe(0)
            ->and($remaining->total()->cents())->toBe(25);
    });

    it('can remove every coin, leaving an empty bag', function () {
        $bag = CoinBag::of(Coin::Dime, Coin::Nickel);

        expect($bag->subtract($bag)->isEmpty())->toBeTrue();
    });

    it('treats an empty bag as neutral', function () {
        $bag = CoinBag::of(Coin::Dollar);

        expect($bag->subtract(CoinBag::empty()))->toEqual($bag);
    });

    it('rejects removing more coins than the bag holds', function () {
        expect(fn() => CoinBag::of(Coin::Dime)->subtract(CoinBag::of(Coin::Dime, Coin::Dime)))
            ->toThrow(InsufficientCoins::class, 'Cannot remove');
    });

    it('rejects removing a coin the bag does not hold', function () {
        expect(fn() => CoinBag::of(Coin::Dime)->subtract(CoinBag::of(Coin::Quarter)))
            ->toThrow(InsufficientCoins::class, 'Cannot remove');
    });

    it('does not modify the operand when it fails', function () {
        $bag = CoinBag::of(Coin::Dime);

        expect(fn() => $bag->subtract(CoinBag::of(Coin::Dime, Coin::Dime)))
            ->toThrow(InsufficientCoins::class)
            ->and($bag->quantityOf(Coin::Dime))->toBe(1);

    });
});
