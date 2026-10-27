<?php

declare(strict_types=1);

use Slim\App;

/*
 * The scenarios from the task, told only through the HTTP API: the operator prepares the machine with SERVICE
 * and a customer then uses it, the way they would in real life.
 */

/**
 * @param array<string, int> $coins
 * @return App<DI\Container>
 */
function machineServicedWith(array $coins): App
{
    $app = httpApp();

    $response = callApi($app, 'PUT', '/machines/default/service', [
        'products' => [
            ['selector' => 'water', 'price' => '0.65', 'quantity' => 10],
            ['selector' => 'juice', 'price' => '1.00', 'quantity' => 10],
            ['selector' => 'soda', 'price' => '1.50', 'quantity' => 10],
        ],
        'coins' => $coins,
    ]);
    expect($response->getStatusCode())->toBe(200);

    return $app;
}

/**
 * @param App<DI\Container> $app
 */
function insert(App $app, string ...$coins): void
{
    foreach ($coins as $coin) {
        expect(callApi($app, 'POST', '/machines/default/coins', ['coin' => $coin])->getStatusCode())->toBe(200);
    }
}

describe('the scenarios from the task', function () {
    it('example 1: a customer buys a soda with exact money', function () {
        $app = machineServicedWith(['0.25' => 10, '0.10' => 10, '0.05' => 10]);

        insert($app, '1', '0.25', '0.25');
        $response = callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'soda']);

        expect($response->getStatusCode())->toBe(200)
            ->and(jsonBody($response))->toBe(['item' => 'SODA', 'change' => []]);
    });

    it('example 2: a customer inserts two dimes and asks for them back', function () {
        $app = machineServicedWith(['0.25' => 10, '0.10' => 10, '0.05' => 10]);

        insert($app, '0.10', '0.10');
        $response = callApi($app, 'DELETE', '/machines/default/coins');

        expect($response->getStatusCode())->toBe(200)
            ->and(jsonBody($response))->toBe(['coins' => ['0.10', '0.10']]);
    });

    it('example 3: a customer pays a dollar for a water and gets a quarter and a dime back', function () {
        $app = machineServicedWith(['0.25' => 2, '0.10' => 2]);

        insert($app, '1');
        $response = callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'water']);

        expect($response->getStatusCode())->toBe(200)
            ->and(jsonBody($response))->toBe(['item' => 'WATER', 'change' => ['0.10', '0.25']]);
    });
});

describe('longer stories', function () {
    it('keeps the money of a customer who changes their mind, then sells to the next one', function () {
        $app = machineServicedWith(['0.25' => 10, '0.10' => 10, '0.05' => 10]);

        insert($app, '1');
        expect(jsonBody(callApi($app, 'DELETE', '/machines/default/coins')))->toBe(['coins' => ['1.00']]);

        insert($app, '1');
        $response = callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'juice']);

        expect(jsonBody($response))->toBe(['item' => 'JUICE', 'change' => []]);
    });

    it('lets a customer add coins after being told they are short', function () {
        $app = machineServicedWith(['0.25' => 10, '0.10' => 10, '0.05' => 10]);

        insert($app, '0.25', '0.25');
        expect(callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'water'])->getStatusCode())->toBe(409);

        insert($app, '0.10', '0.05');
        $response = callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'water']);

        expect(jsonBody($response))->toBe(['item' => 'WATER', 'change' => []]);
    });

    it('uses the coins of the customer to give change when the machine has none of its own', function () {
        $app = machineServicedWith([]);

        insert($app, '0.25', '0.25', '0.10', '0.10', '0.10', '0.05');
        $response = callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'water']);

        expect(jsonBody($response))->toBe(['item' => 'WATER', 'change' => ['0.10', '0.10']]);
    });

    it('refuses the sale and gives the money back when it cannot make change, then works with exact coins', function () {
        $app = machineServicedWith([]);

        insert($app, '1');
        $refused = callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'water']);

        expect($refused->getStatusCode())->toBe(409)
            ->and(jsonBody($refused)['refund'])->toBe(['1.00']);

        insert($app, '0.25', '0.25', '0.10', '0.05');
        $sold = callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'water']);

        expect(jsonBody($sold))->toBe(['item' => 'WATER', 'change' => []]);
    });

    it('does not let the operator wipe the money of a customer in the middle of a purchase', function () {
        $app = machineServicedWith(['0.25' => 10]);

        insert($app, '0.25');
        $service = callApi($app, 'PUT', '/machines/default/service', ['products' => [], 'coins' => []]);

        expect($service->getStatusCode())->toBe(409);
        expect(jsonBody(callApi($app, 'DELETE', '/machines/default/coins')))->toBe(['coins' => ['0.25']]);
    });

    it('runs out of a product after the last one is sold', function () {
        $app = httpApp();
        callApi($app, 'PUT', '/machines/default/service', [
            'products' => [['selector' => 'soda', 'price' => '1.50', 'quantity' => 1]],
            'coins' => [],
        ]);

        insert($app, '1', '0.25', '0.25');
        expect(callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'soda'])->getStatusCode())->toBe(200);

        insert($app, '1', '0.25', '0.25');
        $second = callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'soda']);

        expect($second->getStatusCode())->toBe(409);
        expect(jsonBody(callApi($app, 'GET', '/machines/default'))['products'])
            ->toBe([['selector' => 'SODA', 'price' => '1.50', 'quantity' => 0]]);
    });
});
