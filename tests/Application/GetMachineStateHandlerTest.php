<?php

declare(strict_types=1);

use VendingMachine\Application\GetMachineState\GetMachineStateHandler;
use VendingMachine\Application\GetMachineState\GetMachineStateQuery;
use VendingMachine\Application\InsertCoin\InsertCoinCommand;
use VendingMachine\Application\InsertCoin\InsertCoinHandler;
use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Machine\CoinBag;
use VendingMachine\Domain\Machine\MachineNotFoundException;

describe('GetMachineStateHandler', function () {
    it('lists every product with its price and remaining stock', function () {
        $handler = new GetMachineStateHandler(repositoryWithDefaultMachine());

        $state = $handler->handle(new GetMachineStateQuery('default'));

        expect($state->products)->toBe([
            ['selector' => 'WATER', 'price' => '0.65', 'quantity' => 10],
            ['selector' => 'JUICE', 'price' => '1.00', 'quantity' => 10],
            ['selector' => 'SODA', 'price' => '1.50', 'quantity' => 10],
        ]);
    });

    it('reports how many coins of each kind the machine holds, including none', function () {
        $repository = repositoryHolding(machine(CoinBag::empty()->add(Coin::Quarter, 4)->add(Coin::Dime, 2)));

        $state = new GetMachineStateHandler($repository)->handle(new GetMachineStateQuery('default'));

        expect($state->availableChange)->toBe([
            '0.05' => 0,
            '0.10' => 2,
            '0.25' => 4,
            '1.00' => 0,
        ]);
    });

    it('reports the money a customer has inserted', function () {
        $repository = repositoryWithDefaultMachine();
        $insert = new InsertCoinHandler($repository);
        $insert->handle(new InsertCoinCommand('default', '0.25'));
        $insert->handle(new InsertCoinCommand('default', '0.25'));
        $insert->handle(new InsertCoinCommand('default', '1'));

        $state = new GetMachineStateHandler($repository)->handle(new GetMachineStateQuery('default'));

        expect($state->insertedTotal)->toBe('1.50')
            ->and($state->insertedCoins)->toBe([
                '0.05' => 0,
                '0.10' => 0,
                '0.25' => 2,
                '1.00' => 1,
            ]);
    });

    it('shows nothing inserted for an idle machine', function () {
        $state = new GetMachineStateHandler(repositoryWithDefaultMachine())
            ->handle(new GetMachineStateQuery('default'));

        expect($state->insertedTotal)->toBe('0.00')
            ->and($state->insertedCoins)->toBe(['0.05' => 0, '0.10' => 0, '0.25' => 0, '1.00' => 0]);
    });

    it('does not change the machine', function () {
        $repository = repositoryWithDefaultMachine();
        new InsertCoinHandler($repository)->handle(new InsertCoinCommand('default', '0.25'));
        $handler = new GetMachineStateHandler($repository);

        $first = $handler->handle(new GetMachineStateQuery('default'));
        $second = $handler->handle(new GetMachineStateQuery('default'));

        expect($second)->toEqual($first);
    });

    it('rejects a machine that does not exist', function () {
        $handler = new GetMachineStateHandler(repositoryWithDefaultMachine());

        expect(fn() => $handler->handle(new GetMachineStateQuery('nope')))
            ->toThrow(MachineNotFoundException::class);
    });
});
