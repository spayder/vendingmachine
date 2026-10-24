<?php

declare(strict_types=1);

use VendingMachine\Domain\Machine\BoundedChangeMaker;
use VendingMachine\Domain\Machine\ChangeMaker;
use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Machine\CoinBag;
use VendingMachine\Domain\Shared\Money;

it('is a change maker', function () {
    expect(new BoundedChangeMaker())->toBeInstanceOf(ChangeMaker::class);
});

describe('makeChange', function () {
    it('gives no coins when nothing is owed', function () {
        $change = new BoundedChangeMaker()->makeChange(Money::zero(), CoinBag::empty());

        expect($change)->toEqual(CoinBag::empty());
    });

    it('returns the coins that add up to the amount', function (Money $amount, CoinBag $available, CoinBag $expected) {
        $change = new BoundedChangeMaker()->makeChange($amount, $available);

        expect($change)->toEqual($expected);
    })->with([
        'a quarter and a dime for 0.35 (spec example 3)' => [
            Money::fromCents(35),
            CoinBag::empty()->add(Coin::Quarter, 5)->add(Coin::Dime, 5)->add(Coin::Nickel, 5),
            CoinBag::of(Coin::Quarter, Coin::Dime),
        ],
        'a single nickel' => [
            Money::fromCents(5),
            CoinBag::empty()->add(Coin::Quarter, 5)->add(Coin::Dime, 5)->add(Coin::Nickel, 5),
            CoinBag::of(Coin::Nickel),
        ],
        'prefers larger coins' => [
            Money::fromCents(30),
            CoinBag::empty()->add(Coin::Quarter, 1)->add(Coin::Dime, 5)->add(Coin::Nickel, 5),
            CoinBag::of(Coin::Quarter, Coin::Nickel),
        ],
        'uses what is left when larger coins run out' => [
            Money::fromCents(50),
            CoinBag::empty()->add(Coin::Quarter, 1)->add(Coin::Dime, 5),
            CoinBag::empty()->add(Coin::Dime, 5),
        ],
        'steps back from a quarter that leads nowhere' => [
            Money::fromCents(30),
            CoinBag::empty()->add(Coin::Quarter, 1)->add(Coin::Dime, 3),
            CoinBag::empty()->add(Coin::Dime, 3),
        ],
    ]);

    it('returns null when the amount cannot be paid exactly', function (Money $amount, CoinBag $available) {
        expect((new BoundedChangeMaker())->makeChange($amount, $available))->toBeNull();
    })->with([
        'not enough coins' => [
            Money::fromCents(35),
            CoinBag::of(Coin::Quarter),
        ],
        'no coins at all' => [
            Money::fromCents(5),
            CoinBag::empty(),
        ],
        'amount is not a multiple of the smallest coin' => [
            Money::fromCents(3),
            CoinBag::empty()->add(Coin::Quarter, 5)->add(Coin::Dime, 5)->add(Coin::Nickel, 5),
        ],
        'only the wrong coins are left' => [
            Money::fromCents(10),
            CoinBag::empty()->add(Coin::Quarter, 5),
        ],
        'dollars are never handed back as change' => [
            Money::fromCents(100),
            CoinBag::empty()->add(Coin::Dollar, 5),
        ],
    ]);

    it('never returns more coins than are available', function () {
        $available = CoinBag::empty()->add(Coin::Quarter, 2)->add(Coin::Dime, 2)->add(Coin::Nickel, 2);

        $change = (new BoundedChangeMaker())->makeChange(Money::fromCents(70), $available);

        expect($change)->toBeInstanceOf(CoinBag::class);
        assert($change instanceof CoinBag);

        expect($change->total()->cents())->toBe(70)
            ->and($change->quantityOf(Coin::Quarter))->toBeLessThanOrEqual(2)
            ->and($change->quantityOf(Coin::Dime))->toBeLessThanOrEqual(2)
            ->and($change->quantityOf(Coin::Nickel))->toBeLessThanOrEqual(2);
    });

    it('does not modify the available coins', function () {
        $available = CoinBag::of(Coin::Quarter, Coin::Dime);

        (new BoundedChangeMaker())->makeChange(Money::fromCents(35), $available);

        expect($available)->toEqual(CoinBag::of(Coin::Quarter, Coin::Dime));
    });

    it('copes with a large stock', function () {
        $available = CoinBag::empty()->add(Coin::Quarter, 1000)->add(Coin::Dime, 1000)->add(Coin::Nickel, 1000);

        $change = (new BoundedChangeMaker())->makeChange(Money::fromCents(9995), $available);

        expect($change)->toBeInstanceOf(CoinBag::class);
        assert($change instanceof CoinBag);

        expect($change->total()->cents())->toBe(9995);
    });

    it('gives up quickly when a large stock cannot pay the amount', function () {
        $available = CoinBag::empty()->add(Coin::Quarter, 1000)->add(Coin::Dime, 1000)->add(Coin::Nickel, 1000);

        expect((new BoundedChangeMaker())->makeChange(Money::fromCents(10003), $available))->toBeNull();
    });
});
