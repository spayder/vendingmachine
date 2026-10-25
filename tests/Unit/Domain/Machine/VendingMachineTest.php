<?php

declare(strict_types=1);

use VendingMachine\Domain\Machine\BoundedChangeMaker;
use VendingMachine\Domain\Machine\CannotMakeChange;
use VendingMachine\Domain\Machine\ChangeMaker;
use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Machine\CoinBag;
use VendingMachine\Domain\Machine\InsufficientCoins;
use VendingMachine\Domain\Machine\InsufficientFunds;
use VendingMachine\Domain\Machine\Inventory;
use VendingMachine\Domain\Machine\MachineId;
use VendingMachine\Domain\Machine\OutOfStock;
use VendingMachine\Domain\Machine\Selector;
use VendingMachine\Domain\Machine\UnknownProduct;
use VendingMachine\Domain\Machine\VendingMachine;
use VendingMachine\Domain\Shared\Money;

describe('create', function () {
    it('starts with the id, inventory and available change it was given and nothing inserted', function () {
        $inventory = stockedInventory();
        $availableChange = CoinBag::empty()->add(Coin::Quarter, 4);

        $machine = VendingMachine::create(MachineId::fromString('lobby'), $inventory, $availableChange);

        expect($machine->id()->value())->toBe('lobby')
            ->and($machine->inventory())->toBe($inventory)
            ->and($machine->availableChange())->toEqual($availableChange)
            ->and($machine->inserted()->isEmpty())->toBeTrue();
    });
});

describe('insert', function () {
    it('keeps the coins the customer inserted', function () {
        $machine = machine();

        $machine->insert(Coin::Dollar);
        $machine->insert(Coin::Quarter);
        $machine->insert(Coin::Quarter);

        expect($machine->inserted())->toEqual(CoinBag::of(Coin::Dollar, Coin::Quarter, Coin::Quarter))
            ->and($machine->inserted()->total()->cents())->toBe(150);
    });

    it('keeps inserted coins apart from the available change', function () {
        $machine = machine(CoinBag::of(Coin::Dime));

        $machine->insert(Coin::Quarter);

        expect($machine->availableChange())->toEqual(CoinBag::of(Coin::Dime))
            ->and($machine->inserted())->toEqual(CoinBag::of(Coin::Quarter));
    });
});

describe('returnCoins', function () {
    it('gives back exactly the coins inserted, dollar included (spec example 2)', function () {
        $machine = machine();
        $machine->insert(Coin::Dime);
        $machine->insert(Coin::Dime);

        $returned = $machine->returnCoins();

        expect($returned)->toEqual(CoinBag::of(Coin::Dime, Coin::Dime));
    });

    it('gives back a dollar as a dollar', function () {
        $machine = machine();
        $machine->insert(Coin::Dollar);

        expect($machine->returnCoins())->toEqual(CoinBag::of(Coin::Dollar));
    });

    it('empties the inserted coins', function () {
        $machine = machine();
        $machine->insert(Coin::Quarter);

        $machine->returnCoins();

        expect($machine->inserted()->isEmpty())->toBeTrue();
    });

    it('gives back nothing when nothing was inserted', function () {
        expect(machine()->returnCoins()->isEmpty())->toBeTrue();
    });

    it('does not touch the available change', function () {
        $availableChange = CoinBag::empty()->add(Coin::Nickel, 3);
        $machine = machine($availableChange);
        $machine->insert(Coin::Quarter);

        $machine->returnCoins();

        expect($machine->availableChange())->toEqual($availableChange);
    });
});

describe('purchase', function () {
    describe('when the sale goes through', function () {
        it('vends the item for exact money with no change (spec example 1)', function () {
            $machine = insertCoins(machine(), Coin::Dollar, Coin::Quarter, Coin::Quarter);

            $result = $machine->purchase(Selector::fromString('soda'), new BoundedChangeMaker());

            expect($result->selector()->value())->toBe('SODA')
                ->and($result->change()->isEmpty())->toBeTrue();
        });

        it('vends the item and hands back change (spec example 3)', function () {
            $machine = insertCoins(
                machine(CoinBag::empty()->add(Coin::Quarter, 2)->add(Coin::Dime, 2)),
                Coin::Dollar,
            );

            $result = $machine->purchase(Selector::fromString('water'), new BoundedChangeMaker());

            expect($result->selector()->value())->toBe('WATER')
                ->and($result->change())->toEqual(CoinBag::of(Coin::Quarter, Coin::Dime));
        });

        it('accepts the selector in any case', function () {
            $machine = insertCoins(machine(), Coin::Dollar, Coin::Quarter, Coin::Quarter);

            $result = $machine->purchase(Selector::fromString('SoDa'), new BoundedChangeMaker());

            expect($result->selector()->value())->toBe('SODA');
        });

        it('takes one item out of stock, and only that item', function () {
            $machine = insertCoins(machine(), Coin::Dollar, Coin::Quarter, Coin::Quarter);

            $machine->purchase(Selector::fromString('soda'), new BoundedChangeMaker());

            expect($machine->inventory()->get(Selector::fromString('soda'))->quantity())->toBe(9)
                ->and($machine->inventory()->get(Selector::fromString('water'))->quantity())->toBe(10)
                ->and($machine->inventory()->get(Selector::fromString('juice'))->quantity())->toBe(10);
        });

        it('clears the inserted coins', function () {
            $machine = insertCoins(machine(), Coin::Dollar, Coin::Quarter, Coin::Quarter);

            $machine->purchase(Selector::fromString('soda'), new BoundedChangeMaker());

            expect($machine->inserted()->isEmpty())->toBeTrue();
        });

        it('keeps the paid coins in the available change, minus what was handed back', function () {
            $machine = insertCoins(
                machine(CoinBag::empty()->add(Coin::Quarter, 2)->add(Coin::Dime, 2)),
                Coin::Dollar,
            );

            $machine->purchase(Selector::fromString('water'), new BoundedChangeMaker());

            expect($machine->availableChange())->toEqual(
                CoinBag::empty()->add(Coin::Quarter, 1)->add(Coin::Dime, 1)->add(Coin::Dollar, 1),
            );
        });

        it('keeps the coins of an exact payment', function () {
            $machine = insertCoins(machine(), Coin::Dollar, Coin::Quarter, Coin::Quarter);

            $machine->purchase(Selector::fromString('soda'), new BoundedChangeMaker());

            expect($machine->availableChange())->toEqual(
                CoinBag::empty()->add(Coin::Dollar, 1)->add(Coin::Quarter, 2),
            );
        });

        it('can use the coins just inserted to make the change', function () {
            // Water is 0.65; 0.75 inserted leaves 0.10 owed, and the machine owns no coins at all.
            $machine = insertCoins(machine(), Coin::Quarter, Coin::Quarter, Coin::Dime, Coin::Dime, Coin::Nickel);

            $result = $machine->purchase(Selector::fromString('water'), new BoundedChangeMaker());

            expect($result->change())->toEqual(CoinBag::of(Coin::Dime))
                ->and($machine->availableChange())->toEqual(
                    CoinBag::empty()->add(Coin::Quarter, 2)->add(Coin::Dime, 1)->add(Coin::Nickel, 1),
                );
        });

        it('asks the change maker for the difference, offering available change plus inserted coins', function () {
            $spy = new class implements ChangeMaker {
                public ?Money $asked = null;
                public ?CoinBag $offered = null;

                public function makeChange(Money $amount, CoinBag $available): CoinBag
                {
                    $this->asked = $amount;
                    $this->offered = $available;

                    return CoinBag::of(Coin::Quarter, Coin::Dime);
                }
            };
            $machine = insertCoins(
                machine(CoinBag::of(Coin::Nickel, Coin::Quarter, Coin::Dime)),
                Coin::Dollar,
            );

            $machine->purchase(Selector::fromString('water'), $spy);

            expect($spy->asked?->cents())->toBe(35)
                ->and($spy->offered)->toEqual(CoinBag::of(Coin::Nickel, Coin::Quarter, Coin::Dime, Coin::Dollar));
        });
    });

    describe('when the sale is refused', function () {
        it('rejects a selector the machine does not stock', function () {
            $machine = insertCoins(machine(), Coin::Dollar);

            expect(fn() => $machine->purchase(Selector::fromString('tea'), new BoundedChangeMaker()))
                ->toThrow(UnknownProduct::class, 'Unknown product: TEA');
        });

        it('rejects a sold out item', function () {
            $machine = insertCoins(
                machine(null, Inventory::of(slotOf('water', 65, 0))),
                Coin::Dollar,
            );

            expect(fn() => $machine->purchase(Selector::fromString('water'), new BoundedChangeMaker()))
                ->toThrow(OutOfStock::class, 'Out of stock: WATER');
        });

        it('reports a sold out item before asking for more money', function () {
            $machine = machine(null, Inventory::of(slotOf('water', 65, 0)));

            expect(fn() => $machine->purchase(Selector::fromString('water'), new BoundedChangeMaker()))
                ->toThrow(OutOfStock::class);
        });

        it('rejects a purchase without enough money', function (array $coins) {
            /** @var list<Coin> $coins */
            $machine = insertCoins(machine(), ...$coins);

            expect(fn() => $machine->purchase(Selector::fromString('water'), new BoundedChangeMaker()))
                ->toThrow(InsufficientFunds::class, 'Insufficient funds');
        })->with([
            'nothing inserted' => [[]],
            'half of the price' => [[Coin::Quarter, Coin::Quarter]],
            'a nickel short' => [[Coin::Quarter, Coin::Quarter, Coin::Dime]],
        ]);

        it('rejects a sale when the exact change cannot be given', function () {
            // Water is 0.65, a dollar leaves 0.35 owed, and the machine owns no coins.
            $machine = insertCoins(machine(), Coin::Dollar);

            expect(fn() => $machine->purchase(Selector::fromString('water'), new BoundedChangeMaker()))
                ->toThrow(CannotMakeChange::class, 'Cannot make change');
        });

        it('rejects a sale when the change maker has no answer, whatever the reason', function () {
            $refusing = new class implements ChangeMaker {
                public function makeChange(Money $amount, CoinBag $available): ?CoinBag
                {
                    return null;
                }
            };
            $machine = insertCoins(machine(), Coin::Dollar);

            expect(fn() => $machine->purchase(Selector::fromString('water'), $refusing))
                ->toThrow(CannotMakeChange::class);
        });

        it('changes nothing when the change maker hands out coins the machine does not hold', function () {
            $faulty = new class implements ChangeMaker {
                public function makeChange(Money $amount, CoinBag $available): CoinBag
                {
                    return CoinBag::of(Coin::Quarter, Coin::Dime);
                }
            };
            $machine = insertCoins(machine(), Coin::Dollar);
            $inventoryBefore = $machine->inventory();

            expect(fn() => $machine->purchase(Selector::fromString('water'), $faulty))
                ->toThrow(InsufficientCoins::class);

            expect($machine->inventory())->toBe($inventoryBefore)
                ->and($machine->inventory()->get(Selector::fromString('water'))->quantity())->toBe(10)
                ->and($machine->availableChange()->isEmpty())->toBeTrue()
                ->and($machine->inserted())->toEqual(CoinBag::of(Coin::Dollar));
        });

        it('changes nothing in the machine when refused', function (Closure $prepare, string $selector, string $exception) {
            /** @var VendingMachine $machine */
            $machine = $prepare();
            $inventoryBefore = $machine->inventory();
            $availableChangeBefore = $machine->availableChange();
            $insertedBefore = $machine->inserted();

            expect(fn() => $machine->purchase(Selector::fromString($selector), new BoundedChangeMaker()))
                ->toThrow($exception);

            expect($machine->inventory())->toBe($inventoryBefore)
                ->and($machine->availableChange())->toEqual($availableChangeBefore)
                ->and($machine->inserted())->toEqual($insertedBefore);
        })->with([
            'unknown selector' => [
                fn() => insertCoins(machine(), Coin::Dollar),
                'tea',
                UnknownProduct::class,
            ],
            'sold out' => [
                fn() => insertCoins(machine(null, Inventory::of(slotOf('water', 65, 0))), Coin::Dollar),
                'water',
                OutOfStock::class,
            ],
            'not enough money' => [
                fn() => insertCoins(machine(), Coin::Quarter),
                'water',
                InsufficientFunds::class,
            ],
            'cannot make change' => [
                fn() => insertCoins(machine(), Coin::Dollar),
                'water',
                CannotMakeChange::class,
            ],
        ]);
    });
});
