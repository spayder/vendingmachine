<?php

declare(strict_types=1);

use VendingMachine\Application\InsertCoin\InsertCoinCommand;
use VendingMachine\Application\InsertCoin\InsertCoinHandler;
use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Machine\CoinBag;
use VendingMachine\Domain\Machine\InvalidMachineIdException;
use VendingMachine\Domain\Machine\MachineId;
use VendingMachine\Domain\Machine\MachineNotFoundException;
use VendingMachine\Domain\Machine\UnsupportedCoinException;
use VendingMachine\Domain\Shared\InvalidMoneyException;

describe('InsertCoinHandler', function () {
    it('puts the coin into the machine and saves it', function (string $coin, Coin $expected) {
        $repository = repositoryWithDefaultMachine();

        new InsertCoinHandler($repository)->handle(new InsertCoinCommand('default', $coin));

        expect($repository->get(MachineId::fromString('default'))->inserted())->toEqual(CoinBag::of($expected));
    })->with([
        'nickel' => ['0.05', Coin::Nickel],
        'dime' => ['0.10', Coin::Dime],
        'quarter' => ['0.25', Coin::Quarter],
        'dollar' => ['1', Coin::Dollar],
        'dollar with decimals' => ['1.00', Coin::Dollar],
    ]);

    it('reports the coins inserted so far', function () {
        $handler = new InsertCoinHandler(repositoryWithDefaultMachine());

        $afterFirst = $handler->handle(new InsertCoinCommand('default', '1'));
        $afterSecond = $handler->handle(new InsertCoinCommand('default', '0.25'));
        $afterThird = $handler->handle(new InsertCoinCommand('default', '0.25'));

        expect($afterFirst)->toEqual(CoinBag::of(Coin::Dollar))
            ->and($afterSecond)->toEqual(CoinBag::of(Coin::Dollar, Coin::Quarter))
            ->and($afterThird)->toEqual(CoinBag::of(Coin::Dollar, Coin::Quarter, Coin::Quarter))
            ->and($afterThird->total()->toString())->toBe('1.50');
    });

    it('keeps the coins of earlier requests', function () {
        $repository = repositoryWithDefaultMachine();
        $handler = new InsertCoinHandler($repository);

        $handler->handle(new InsertCoinCommand('default', '0.10'));
        $handler->handle(new InsertCoinCommand('default', '0.10'));

        expect($repository->get(MachineId::fromString('default'))->inserted())
            ->toEqual(CoinBag::of(Coin::Dime, Coin::Dime));
    });

    it('only touches the machine it was asked for', function () {
        $repository = repositoryWithDefaultMachine();
        $repository->save(machineWithId('other'));

        (new InsertCoinHandler($repository))->handle(new InsertCoinCommand('other', '0.25'));

        expect($repository->get(MachineId::fromString('default'))->inserted()->isEmpty())->toBeTrue()
            ->and($repository->get(MachineId::fromString('other'))->inserted())->toEqual(CoinBag::of(Coin::Quarter));
    });

    it('rejects a machine that does not exist', function () {
        $handler = new InsertCoinHandler(repositoryWithDefaultMachine());

        expect(fn() => $handler->handle(new InsertCoinCommand('nope', '0.25')))
            ->toThrow(MachineNotFoundException::class);
    });

    it('rejects a machine id that is not valid', function () {
        $handler = new InsertCoinHandler(repositoryWithDefaultMachine());

        expect(fn() => $handler->handle(new InsertCoinCommand('not valid!', '0.25')))
            ->toThrow(InvalidMachineIdException::class);
    });

    it('rejects an amount that is not a coin and stores nothing', function (string $coin, string $exception) {
        $repository = repositoryWithDefaultMachine();
        $handler = new InsertCoinHandler($repository);

        expect(fn() => $handler->handle(new InsertCoinCommand('default', $coin)))->toThrow($exception);

        expect($repository->get(MachineId::fromString('default'))->inserted()->isEmpty())->toBeTrue();
    })->with([
        'valid amount, no such coin' => ['0.50', UnsupportedCoinException::class],
        'two dollars' => ['2', UnsupportedCoinException::class],
        'not a number' => ['quarter', InvalidMoneyException::class],
        'empty' => ['', InvalidMoneyException::class],
        'float style' => ['0.250', InvalidMoneyException::class],
    ]);
});
