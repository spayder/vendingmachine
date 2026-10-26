<?php

declare(strict_types=1);

use VendingMachine\Application\InsertCoin\InsertCoinCommand;
use VendingMachine\Application\InsertCoin\InsertCoinHandler;
use VendingMachine\Application\Purchase\PurchaseCommand;
use VendingMachine\Application\Purchase\PurchaseHandler;
use VendingMachine\Application\Purchase\SaleRefundedException;
use VendingMachine\Domain\Machine\BoundedChangeMaker;
use VendingMachine\Domain\Machine\CannotMakeChangeException;
use VendingMachine\Domain\Machine\ChangeMaker;
use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Machine\CoinBag;
use VendingMachine\Domain\Machine\InsufficientFundsException;
use VendingMachine\Domain\Machine\InvalidSelectorException;
use VendingMachine\Domain\Machine\Inventory;
use VendingMachine\Domain\Machine\MachineId;
use VendingMachine\Domain\Machine\MachineNotFoundException;
use VendingMachine\Domain\Machine\OutOfStockException;
use VendingMachine\Domain\Machine\Selector;
use VendingMachine\Domain\Machine\UnknownProductException;
use VendingMachine\Domain\Machine\VendingMachine;
use VendingMachine\Domain\Shared\Money;
use VendingMachine\Tests\Support\InMemoryMachineRepository;

/**
 * A machine holding some coins of its own, stocked like the one in the spec.
 */
function repositoryWithChange(CoinBag $availableChange): InMemoryMachineRepository
{
    return repositoryHolding(machine($availableChange));
}

function storedMachine(InMemoryMachineRepository $repository): VendingMachine
{
    return $repository->get(MachineId::fromString('default'));
}

function insertViaHandler(InMemoryMachineRepository $repository, string ...$coins): void
{
    $insert = new InsertCoinHandler($repository);

    foreach ($coins as $coin) {
        $insert->handle(new InsertCoinCommand('default', $coin));
    }
}

describe('PurchaseHandler', function () {
    describe('when the sale goes through', function () {
        it('vends the item for exact money (spec example 1)', function () {
            $repository = repositoryWithChange(CoinBag::empty());
            insertViaHandler($repository, '1', '0.25', '0.25');

            $result = new PurchaseHandler($repository, new BoundedChangeMaker())
                ->handle(new PurchaseCommand('default', 'soda'));

            expect($result->selector()->value())->toBe('SODA')
                ->and($result->change()->isEmpty())->toBeTrue();
        });

        it('vends the item and returns change (spec example 3)', function () {
            $repository = repositoryWithChange(CoinBag::empty()->add(Coin::Quarter, 2)->add(Coin::Dime, 2));
            insertViaHandler($repository, '1');

            $result = new PurchaseHandler($repository, new BoundedChangeMaker())
                ->handle(new PurchaseCommand('default', 'water'));

            expect($result->selector()->value())->toBe('WATER')
                ->and($result->change())->toEqual(CoinBag::of(Coin::Quarter, Coin::Dime));
        });

        it('saves the machine after the sale', function () {
            $repository = repositoryWithChange(CoinBag::empty()->add(Coin::Quarter, 2)->add(Coin::Dime, 2));
            insertViaHandler($repository, '1');

            new PurchaseHandler($repository, new BoundedChangeMaker())
                ->handle(new PurchaseCommand('default', 'water'));

            $machine = storedMachine($repository);

            expect($machine->inventory()->get(Selector::fromString('water'))->quantity())->toBe(9)
                ->and($machine->inserted()->isEmpty())->toBeTrue()
                ->and($machine->availableChange())->toEqual(
                    CoinBag::empty()->add(Coin::Quarter, 1)->add(Coin::Dime, 1)->add(Coin::Dollar, 1),
                );
        });

        it('sells the same product to one customer after another', function () {
            $repository = repositoryWithChange(CoinBag::empty());
            $handler = new PurchaseHandler($repository, new BoundedChangeMaker());

            insertViaHandler($repository, '1', '0.25', '0.25');
            $handler->handle(new PurchaseCommand('default', 'soda'));
            insertViaHandler($repository, '1', '0.25', '0.25');
            $handler->handle(new PurchaseCommand('default', 'soda'));

            expect(storedMachine($repository)->inventory()->get(Selector::fromString('soda'))->quantity())->toBe(8);
        });
    });

    describe('when the sale is refused and the coins stay inserted', function () {
        it('rejects a machine that does not exist', function () {
            $handler = new PurchaseHandler(repositoryWithChange(CoinBag::empty()), new BoundedChangeMaker());

            expect(fn() => $handler->handle(new PurchaseCommand('nope', 'water')))
                ->toThrow(MachineNotFoundException::class);
        });

        it('rejects a selector that is not valid', function () {
            $handler = new PurchaseHandler(repositoryWithChange(CoinBag::empty()), new BoundedChangeMaker());

            expect(fn() => $handler->handle(new PurchaseCommand('default', 'not valid!')))
                ->toThrow(InvalidSelectorException::class);
        });

        it('keeps the customer\'s coins and the stock untouched', function (string $selector, array $coins, string $exception) {
            /** @var list<string> $coins */
            $repository = repositoryHolding(machine(CoinBag::empty(), Inventory::of(
                slotOf('water', 65, 10),
                slotOf('sold-out', 50, 0),
            )));
            insertViaHandler($repository, ...$coins);
            $handler = new PurchaseHandler($repository, new BoundedChangeMaker());

            expect(fn() => $handler->handle(new PurchaseCommand('default', $selector)))->toThrow($exception);

            $machine = storedMachine($repository);

            expect($machine->inserted()->total()->cents())->toBe(
                array_sum(array_map(fn(string $coin): int => Money::fromString($coin)->cents(), $coins)),
            )
                ->and($machine->inventory()->get(Selector::fromString('water'))->quantity())->toBe(10);
        })->with([
            'unknown product' => ['tea', ['1'], UnknownProductException::class],
            'sold out' => ['sold-out', ['1'], OutOfStockException::class],
            'not enough money' => ['water', ['0.25'], InsufficientFundsException::class],
        ]);
    });

    describe('when exact change cannot be given', function () {
        it('refunds the inserted coins and says so', function () {
            $repository = repositoryWithChange(CoinBag::empty());
            insertViaHandler($repository, '1');
            $handler = new PurchaseHandler($repository, new BoundedChangeMaker());

            $exception = exceptionThrownBy(fn() => $handler->handle(new PurchaseCommand('default', 'water')));

            expect($exception)->toBeInstanceOf(SaleRefundedException::class);
            assert($exception instanceof SaleRefundedException);

            expect($exception->refund())->toEqual(CoinBag::of(Coin::Dollar))
                ->and($exception->getPrevious())->toBeInstanceOf(CannotMakeChangeException::class)
                ->and($exception->getMessage())->toBe('Cannot make change: 0.35 owed; 1.00 refunded');
        });

        it('saves the machine with nothing inserted and the stock unchanged', function () {
            $repository = repositoryWithChange(CoinBag::of(Coin::Nickel));
            insertViaHandler($repository, '1');
            $handler = new PurchaseHandler($repository, new BoundedChangeMaker());

            expect(fn() => $handler->handle(new PurchaseCommand('default', 'water')))
                ->toThrow(SaleRefundedException::class);

            $machine = storedMachine($repository);

            expect($machine->inserted()->isEmpty())->toBeTrue()
                ->and($machine->inventory()->get(Selector::fromString('water'))->quantity())->toBe(10)
                ->and($machine->availableChange())->toEqual(CoinBag::of(Coin::Nickel));
        });

        it('refunds whatever change maker it was given says no', function () {
            $refusing = new class implements ChangeMaker {
                public function makeChange(Money $amount, CoinBag $available): ?CoinBag
                {
                    return null;
                }
            };
            $repository = repositoryWithChange(CoinBag::empty()->add(Coin::Quarter, 5)->add(Coin::Dime, 5));
            insertViaHandler($repository, '1');
            $handler = new PurchaseHandler($repository, $refusing);

            expect(fn() => $handler->handle(new PurchaseCommand('default', 'water')))
                ->toThrow(SaleRefundedException::class);
        });

        it('lets the customer try again after the refund', function () {
            $repository = repositoryWithChange(CoinBag::empty());
            $handler = new PurchaseHandler($repository, new BoundedChangeMaker());
            insertViaHandler($repository, '1');
            expect(fn() => $handler->handle(new PurchaseCommand('default', 'water')))
                ->toThrow(SaleRefundedException::class);

            insertViaHandler($repository, '0.25', '0.25', '0.10', '0.05');
            $result = $handler->handle(new PurchaseCommand('default', 'water'));

            expect($result->selector()->value())->toBe('WATER')
                ->and($result->change()->isEmpty())->toBeTrue();
        });
    });
});
