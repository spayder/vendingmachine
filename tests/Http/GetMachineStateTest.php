<?php

declare(strict_types=1);

use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Machine\CoinBag;

describe('GET /machines/{id}', function () {
    it('describes a machine nobody has touched', function () {
        $response = callApi(httpApp(), 'GET', '/machines/default');

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getHeaderLine('Content-Type'))->toStartWith('application/json')
            ->and(jsonBody($response))->toBe([
                'products' => [
                    ['selector' => 'WATER', 'price' => '0.65', 'quantity' => 10],
                    ['selector' => 'JUICE', 'price' => '1.00', 'quantity' => 10],
                    ['selector' => 'SODA', 'price' => '1.50', 'quantity' => 10],
                ],
                'availableChange' => ['0.05' => 0, '0.10' => 0, '0.25' => 0, '1.00' => 0],
                'inserted' => [
                    'coins' => ['0.05' => 0, '0.10' => 0, '0.25' => 0, '1.00' => 0],
                    'total' => '0.00',
                ],
            ]);
    });

    it('shows the coins the machine holds', function () {
        $app = httpApp(machine(CoinBag::empty()->add(Coin::Quarter, 4)->add(Coin::Dime, 2)));

        $state = jsonBody(callApi($app, 'GET', '/machines/default'));

        expect($state['availableChange'])->toBe(['0.05' => 0, '0.10' => 2, '0.25' => 4, '1.00' => 0]);
    });

    it('shows what a customer has inserted so far', function () {
        $app = httpApp();
        callApi($app, 'POST', '/machines/default/coins', ['coin' => '0.25']);
        callApi($app, 'POST', '/machines/default/coins', ['coin' => '0.25']);
        callApi($app, 'POST', '/machines/default/coins', ['coin' => '1']);

        $state = jsonBody(callApi($app, 'GET', '/machines/default'));

        expect($state['inserted'])->toBe([
            'coins' => ['0.05' => 0, '0.10' => 0, '0.25' => 2, '1.00' => 1],
            'total' => '1.50',
        ]);
    });

    it('shows the stock and the change after a sale', function () {
        $app = httpApp();
        foreach (['0.25', '0.25', '0.10', '0.05'] as $coin) {
            callApi($app, 'POST', '/machines/default/coins', ['coin' => $coin]);
        }
        callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'water']);

        $state = jsonBody(callApi($app, 'GET', '/machines/default'));

        expect($state['products'])->toContain(['selector' => 'WATER', 'price' => '0.65', 'quantity' => 9])
            ->and($state['availableChange'])->toBe(['0.05' => 1, '0.10' => 1, '0.25' => 2, '1.00' => 0])
            ->and($state['inserted'])->toMatchArray(['total' => '0.00']);
    });

    it('does not change anything by being asked', function () {
        $app = httpApp();
        callApi($app, 'POST', '/machines/default/coins', ['coin' => '0.25']);

        $first = jsonBody(callApi($app, 'GET', '/machines/default'));
        $second = jsonBody(callApi($app, 'GET', '/machines/default'));

        expect($second)->toBe($first);
    });

    it('answers 404 for a machine that does not exist', function (string $path) {
        $response = callApi(httpApp(), 'GET', $path);

        expect($response->getStatusCode())->toBe(404)
            ->and($response->getHeaderLine('Content-Type'))->toStartWith('application/problem+json');
    })->with([
        'unknown machine' => ['/machines/nope'],
        'id that can never exist' => ['/machines/bad!id'],
    ]);
});
