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
