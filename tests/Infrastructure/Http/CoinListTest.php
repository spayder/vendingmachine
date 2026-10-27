<?php

declare(strict_types=1);

use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Machine\CoinBag;
use VendingMachine\Infrastructure\Http\CoinList;

describe('CoinList', function () {
    it('is empty for no coins', function () {
        expect(CoinList::of(CoinBag::empty()))->toBe([]);
    });

    it('repeats each coin as often as it is held, smallest first', function () {
        $bag = CoinBag::empty()->add(Coin::Dollar)->add(Coin::Dime, 2)->add(Coin::Quarter);

        expect(CoinList::of($bag))->toBe(['0.10', '0.10', '0.25', '1.00']);
    });
});
