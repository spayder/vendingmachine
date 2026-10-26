<?php

declare(strict_types=1);

use VendingMachine\Application\InsertCoin\InsertCoinCommand;
use VendingMachine\Application\InsertCoin\InsertCoinHandler;
use VendingMachine\Application\ServiceMachine\ServiceMachineCommand;
use VendingMachine\Application\ServiceMachine\ServiceMachineHandler;
use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Machine\CoinBag;
use VendingMachine\Domain\Machine\DuplicateProductException;
use VendingMachine\Domain\Machine\InvalidCoinQuantityException;
use VendingMachine\Domain\Machine\InvalidSelectorException;
use VendingMachine\Domain\Machine\InvalidStockException;
use VendingMachine\Domain\Machine\MachineId;
use VendingMachine\Domain\Machine\MachineNotFoundException;
use VendingMachine\Domain\Machine\Selector;
use VendingMachine\Domain\Machine\SessionInProgressException;
use VendingMachine\Domain\Machine\UnsupportedCoinException;
use VendingMachine\Domain\Shared\InvalidMoneyException;

describe('ServiceMachineHandler', function () {
    it('sets the available change and the item counts (spec: SERVICE)', function () {
        $repository = repositoryWithDefaultMachine();

        new ServiceMachineHandler($repository)->handle(new ServiceMachineCommand(
            'default',
            [
                ['selector' => 'water', 'price' => '0.65', 'quantity' => 5],
                ['selector' => 'tea', 'price' => '2.00', 'quantity' => 3],
            ],
            ['0.25' => 4, '0.10' => 10, '0.05' => 2],
        ));

        $machine = $repository->get(MachineId::fromString('default'));

        expect($machine->availableChange())->toEqual(
            CoinBag::empty()->add(Coin::Quarter, 4)->add(Coin::Dime, 10)->add(Coin::Nickel, 2),
        )
            ->and($machine->inventory()->get(Selector::fromString('water'))->quantity())->toBe(5)
            ->and($machine->inventory()->get(Selector::fromString('tea'))->price()->toString())->toBe('2.00')
            ->and($machine->inventory()->get(Selector::fromString('tea'))->quantity())->toBe(3)
            ->and($machine->inventory()->has(Selector::fromString('soda')))->toBeFalse();
    });

    it('replaces the previous setup instead of adding to it', function () {
        $repository = repositoryWithDefaultMachine();
        $handler = new ServiceMachineHandler($repository);

        $handler->handle(new ServiceMachineCommand('default', [
            ['selector' => 'water', 'price' => '0.65', 'quantity' => 5],
        ], ['0.25' => 4]));
        $handler->handle(new ServiceMachineCommand('default', [
            ['selector' => 'water', 'price' => '0.65', 'quantity' => 5],
        ], ['0.25' => 4]));

        $machine = $repository->get(MachineId::fromString('default'));

        expect($machine->inventory()->get(Selector::fromString('water'))->quantity())->toBe(5)
            ->and($machine->availableChange())->toEqual(CoinBag::empty()->add(Coin::Quarter, 4));
    });

    it('accepts dollars among the coins', function () {
        $repository = repositoryWithDefaultMachine();

        new ServiceMachineHandler($repository)->handle(new ServiceMachineCommand('default', [], ['1' => 2]));

        expect($repository->get(MachineId::fromString('default'))->availableChange())
            ->toEqual(CoinBag::empty()->add(Coin::Dollar, 2));
    });

    it('can empty the machine', function () {
        $repository = repositoryHolding(machine(CoinBag::of(Coin::Quarter)));

        new ServiceMachineHandler($repository)->handle(new ServiceMachineCommand('default', [], []));

        $machine = $repository->get(MachineId::fromString('default'));

        expect($machine->inventory()->all())->toBe([])
            ->and($machine->availableChange()->isEmpty())->toBeTrue();
    });

    it('treats a zero count as no coins of that kind', function () {
        $repository = repositoryWithDefaultMachine();

        new ServiceMachineHandler($repository)->handle(
            new ServiceMachineCommand('default', [], ['0.25' => 0, '0.10' => 3]),
        );

        expect($repository->get(MachineId::fromString('default'))->availableChange())
            ->toEqual(CoinBag::empty()->add(Coin::Dime, 3));
    });

    it('rejects a machine that does not exist', function () {
        $handler = new ServiceMachineHandler(repositoryWithDefaultMachine());

        expect(fn() => $handler->handle(new ServiceMachineCommand('nope', [], [])))
            ->toThrow(MachineNotFoundException::class);
    });

    describe('while a customer has money inserted', function () {
        it('is rejected and leaves the machine untouched', function () {
            $repository = repositoryWithDefaultMachine();
            new InsertCoinHandler($repository)->handle(new InsertCoinCommand('default', '0.25'));
            $handler = new ServiceMachineHandler($repository);

            expect(fn() => $handler->handle(new ServiceMachineCommand('default', [], ['0.25' => 4])))
                ->toThrow(SessionInProgressException::class);

            $machine = $repository->get(MachineId::fromString('default'));

            expect($machine->inserted())->toEqual(CoinBag::of(Coin::Quarter))
                ->and($machine->availableChange()->isEmpty())->toBeTrue()
                ->and($machine->inventory()->get(Selector::fromString('water'))->quantity())->toBe(10);
        });
    });

    describe('with invalid input', function () {
        it('rejects it and leaves the machine untouched', function (array $products, array $coins, string $exception) {
            /** @var list<array{selector: string, price: string, quantity: int}> $products */
            /** @var array<string, int> $coins */
            $repository = repositoryWithDefaultMachine();
            $handler = new ServiceMachineHandler($repository);

            expect(fn() => $handler->handle(new ServiceMachineCommand('default', $products, $coins)))
                ->toThrow($exception);

            $machine = $repository->get(MachineId::fromString('default'));

            expect($machine->inventory()->all())->toHaveCount(3)
                ->and($machine->availableChange()->isEmpty())->toBeTrue();
        })->with([
            'invalid selector' => [[['selector' => 'not valid!', 'price' => '1.00', 'quantity' => 1]], [], InvalidSelectorException::class],
            'duplicate selector' => [[
                ['selector' => 'water', 'price' => '0.65', 'quantity' => 1],
                ['selector' => 'WATER', 'price' => '0.70', 'quantity' => 1],
            ], [], DuplicateProductException::class],
            'malformed price' => [[['selector' => 'water', 'price' => 'abc', 'quantity' => 1]], [], InvalidMoneyException::class],
            'negative stock' => [[['selector' => 'water', 'price' => '0.65', 'quantity' => -1]], [], InvalidStockException::class],
            'unsupported coin' => [[], ['0.02' => 1], UnsupportedCoinException::class],
            'negative coin count' => [[], ['0.25' => -1], InvalidCoinQuantityException::class],
        ]);
    });
});
