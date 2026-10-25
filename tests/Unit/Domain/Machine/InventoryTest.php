<?php

declare(strict_types=1);

use VendingMachine\Domain\Machine\DuplicateProductException;
use VendingMachine\Domain\Machine\Inventory;
use VendingMachine\Domain\Machine\Selector;
use VendingMachine\Domain\Machine\UnknownProductException;

describe('empty', function () {
    it('holds no slots', function () {
        expect(Inventory::empty()->all())->toBe([]);
    });

    it('has no product for any selector', function () {
        expect(Inventory::empty()->has(Selector::fromString('water')))->toBeFalse();
    });
});

describe('of', function () {
    it('keeps the slots in the order given', function () {
        $water = slotOf('water', 65, 5);
        $juice = slotOf('juice', 100, 3);
        $soda = slotOf('soda', 150, 2);

        expect(Inventory::of($water, $juice, $soda)->all())->toBe([$water, $juice, $soda]);
    });

    it('rejects two slots with the same selector', function () {
        expect(fn() => Inventory::of(
            slotOf('water', 65, 5),
            slotOf('WATER', 70, 1),
        ))->toThrow(DuplicateProductException::class, 'Duplicate product: WATER');
    });
});

describe('has', function () {
    it('tells whether a product is stocked, whatever case the selector was written in', function (string $code, bool $expected) {
        $inventory = Inventory::of(slotOf('water', 65, 5));

        expect($inventory->has(Selector::fromString($code)))->toBe($expected);
    })->with([
        'same case' => ['WATER', true],
        'other case' => ['water', true],
        'different product' => ['juice', false],
    ]);

    it('is still true for a sold out product', function () {
        $inventory = Inventory::of(slotOf('water', 65, 0));

        expect($inventory->has(Selector::fromString('water')))->toBeTrue();
    });
});

describe('get', function () {
    it('returns the slot for the selector', function () {
        $water = slotOf('water', 65, 5);
        $inventory = Inventory::of($water, slotOf('juice', 100, 3));

        expect($inventory->get(Selector::fromString('water')))->toBe($water);
    });

    it('rejects a selector that is not stocked', function () {
        $inventory = Inventory::of(slotOf('water', 65, 5));

        expect(fn() => $inventory->get(Selector::fromString('tea')))
            ->toThrow(UnknownProductException::class, 'Unknown product: TEA');
    });
});

describe('replace', function () {
    it('swaps in the updated slot and keeps its position', function () {
        $water = slotOf('water', 65, 5);
        $juice = slotOf('juice', 100, 3);
        $soda = slotOf('soda', 150, 2);
        $lessJuice = $juice->dispenseOne();

        $updated = Inventory::of($water, $juice, $soda)->replace($lessJuice);

        expect($updated->all())->toBe([$water, $lessJuice, $soda]);
    });

    it('does not modify the original inventory', function () {
        $water = slotOf('water', 65, 5);
        $original = Inventory::of($water);

        $original->replace($water->dispenseOne());

        expect($original->get(Selector::fromString('water'))->quantity())->toBe(5);
    });

    it('rejects a slot whose selector is not stocked, instead of adding it', function () {
        $inventory = Inventory::of(slotOf('water', 65, 5));

        expect(fn() => $inventory->replace(slotOf('tea', 90, 4)))
            ->toThrow(UnknownProductException::class, 'Unknown product: TEA');
    });
});
