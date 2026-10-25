<?php

declare(strict_types=1);

use VendingMachine\Application\InsertCoin\InsertCoinCommand;
use VendingMachine\Application\InsertCoin\InsertCoinHandler;
use VendingMachine\Application\ReturnCoin\ReturnCoinCommand;
use VendingMachine\Application\ReturnCoin\ReturnCoinHandler;
use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Machine\CoinBag;
use VendingMachine\Domain\Machine\MachineId;
use VendingMachine\Domain\Machine\MachineNotFoundException;

describe('ReturnCoinHandler', function () {
    it('gives back exactly the coins that were inserted (spec example 2)', function () {
        $repository = repositoryWithDefaultMachine();
        $insert = new InsertCoinHandler($repository);
        $insert->handle(new InsertCoinCommand('default', '0.10'));
        $insert->handle(new InsertCoinCommand('default', '0.10'));

        $returned = (new ReturnCoinHandler($repository))->handle(new ReturnCoinCommand('default'));

        expect($returned)->toEqual(CoinBag::of(Coin::Dime, Coin::Dime));
    });

    it('gives a dollar back as a dollar', function () {
        $repository = repositoryWithDefaultMachine();
        (new InsertCoinHandler($repository))->handle(new InsertCoinCommand('default', '1'));

        $returned = (new ReturnCoinHandler($repository))->handle(new ReturnCoinCommand('default'));

        expect($returned)->toEqual(CoinBag::of(Coin::Dollar));
    });

    it('saves the machine with nothing inserted', function () {
        $repository = repositoryWithDefaultMachine();
        (new InsertCoinHandler($repository))->handle(new InsertCoinCommand('default', '0.25'));

        (new ReturnCoinHandler($repository))->handle(new ReturnCoinCommand('default'));

        expect($repository->get(MachineId::fromString('default'))->inserted()->isEmpty())->toBeTrue();
    });

    it('gives back nothing when nothing was inserted', function () {
        $returned = (new ReturnCoinHandler(repositoryWithDefaultMachine()))->handle(new ReturnCoinCommand('default'));

        expect($returned->isEmpty())->toBeTrue();
    });

    it('does not return the same coins twice', function () {
        $repository = repositoryWithDefaultMachine();
        (new InsertCoinHandler($repository))->handle(new InsertCoinCommand('default', '0.25'));
        $handler = new ReturnCoinHandler($repository);

        $handler->handle(new ReturnCoinCommand('default'));
        $second = $handler->handle(new ReturnCoinCommand('default'));

        expect($second->isEmpty())->toBeTrue();
    });

    it('rejects a machine that does not exist', function () {
        $handler = new ReturnCoinHandler(repositoryWithDefaultMachine());

        expect(fn() => $handler->handle(new ReturnCoinCommand('nope')))
            ->toThrow(MachineNotFoundException::class);
    });
});
