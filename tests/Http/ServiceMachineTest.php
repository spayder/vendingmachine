<?php

declare(strict_types=1);

describe('PUT /machines/{id}/service', function () {
    describe('when the operator sets the machine up', function () {
        it('sets the stock and the change and answers with the new state', function () {
            $response = callApi(httpApp(), 'PUT', '/machines/default/service', [
                'products' => [
                    ['selector' => 'water', 'price' => '0.65', 'quantity' => 5],
                    ['selector' => 'tea', 'price' => '2.00', 'quantity' => 3],
                ],
                'coins' => ['0.25' => 4, '0.10' => 10, '0.05' => 2],
            ]);

            expect($response->getStatusCode())->toBe(200)
                ->and($response->getHeaderLine('Content-Type'))->toStartWith('application/json')
                ->and(jsonBody($response))->toBe([
                    'products' => [
                        ['selector' => 'WATER', 'price' => '0.65', 'quantity' => 5],
                        ['selector' => 'TEA', 'price' => '2.00', 'quantity' => 3],
                    ],
                    'availableChange' => ['0.05' => 2, '0.10' => 10, '0.25' => 4, '1.00' => 0],
                    'inserted' => [
                        'coins' => ['0.05' => 0, '0.10' => 0, '0.25' => 0, '1.00' => 0],
                        'total' => '0.00',
                    ],
                ]);
        });

        it('keeps the setup for later requests', function () {
            $app = httpApp();
            callApi($app, 'PUT', '/machines/default/service', [
                'products' => [['selector' => 'tea', 'price' => '2.00', 'quantity' => 3]],
                'coins' => ['0.25' => 4],
            ]);

            $state = jsonBody(callApi($app, 'GET', '/machines/default'));

            expect($state['products'])->toBe([['selector' => 'TEA', 'price' => '2.00', 'quantity' => 3]])
                ->and($state['availableChange'])->toBe(['0.05' => 0, '0.10' => 0, '0.25' => 4, '1.00' => 0]);
        });

        it('replaces what was there instead of adding to it', function () {
            $app = httpApp();
            $setup = [
                'products' => [['selector' => 'water', 'price' => '0.65', 'quantity' => 5]],
                'coins' => ['0.25' => 4],
            ];

            callApi($app, 'PUT', '/machines/default/service', $setup);
            $second = callApi($app, 'PUT', '/machines/default/service', $setup);

            expect(jsonBody($second)['availableChange'])->toBe(['0.05' => 0, '0.10' => 0, '0.25' => 4, '1.00' => 0]);
        });

        it('accepts dollars written as 1 or as 1.00', function (string $key) {
            $response = callApi(httpApp(), 'PUT', '/machines/default/service', [
                'products' => [],
                'coins' => [$key => 2],
            ]);

            expect(jsonBody($response)['availableChange'])->toBe(['0.05' => 0, '0.10' => 0, '0.25' => 0, '1.00' => 2]);
        })->with(['1', '1.00']);

        it('can empty the machine', function () {
            $response = callApi(httpApp(), 'PUT', '/machines/default/service', ['products' => [], 'coins' => []]);

            expect($response->getStatusCode())->toBe(200)
                ->and(jsonBody($response)['products'])->toBe([])
                ->and(jsonBody($response)['availableChange'])->toBe(['0.05' => 0, '0.10' => 0, '0.25' => 0, '1.00' => 0]);
        });

        it('makes the new change and stock available to the next customer', function () {
            $app = httpApp();
            callApi($app, 'PUT', '/machines/default/service', [
                'products' => [['selector' => 'chips', 'price' => '0.30', 'quantity' => 1]],
                'coins' => ['0.10' => 2],
            ]);
            callApi($app, 'POST', '/machines/default/coins', ['coin' => '0.25']);
            callApi($app, 'POST', '/machines/default/coins', ['coin' => '0.25']);

            $response = callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'chips']);

            expect($response->getStatusCode())->toBe(200)
                ->and(jsonBody($response))->toBe(['item' => 'CHIPS', 'change' => ['0.10', '0.10']]);
        });
    });

    describe('while a customer has money inserted', function () {
        it('answers 409 and leaves the machine as it was', function () {
            $app = httpApp();
            callApi($app, 'POST', '/machines/default/coins', ['coin' => '0.25']);

            $response = callApi($app, 'PUT', '/machines/default/service', [
                'products' => [],
                'coins' => ['0.25' => 4],
            ]);

            expect($response->getStatusCode())->toBe(409)
                ->and($response->getHeaderLine('Content-Type'))->toStartWith('application/problem+json');

            $state = jsonBody(callApi($app, 'GET', '/machines/default'));

            expect($state['products'])->toHaveCount(3)
                ->and($state['inserted'])->toMatchArray(['total' => '0.25']);
        });
    });

    describe('when a value is not acceptable', function () {
        it('answers 422 and leaves the machine as it was', function (array $setup) {
            /** @var array<string, mixed> $setup */
            $app = httpApp();

            $response = callApi($app, 'PUT', '/machines/default/service', $setup);

            expect($response->getStatusCode())->toBe(422)
                ->and($response->getHeaderLine('Content-Type'))->toStartWith('application/problem+json');
            expect(jsonBody(callApi($app, 'GET', '/machines/default'))['products'])->toHaveCount(3);
        })->with([
            'invalid selector' => [['products' => [['selector' => 'not valid!', 'price' => '1.00', 'quantity' => 1]], 'coins' => []]],
            'the same selector twice' => [['products' => [
                ['selector' => 'water', 'price' => '0.65', 'quantity' => 1],
                ['selector' => 'WATER', 'price' => '0.70', 'quantity' => 1],
            ], 'coins' => []]],
            'price that is not money' => [['products' => [['selector' => 'water', 'price' => 'abc', 'quantity' => 1]], 'coins' => []]],
            'negative stock' => [['products' => [['selector' => 'water', 'price' => '0.65', 'quantity' => -1]], 'coins' => []]],
            'coin that does not exist' => [['products' => [], 'coins' => ['0.02' => 1]]],
            'negative number of coins' => [['products' => [], 'coins' => ['0.25' => -1]]],
        ]);
    });

    describe('when the request is malformed', function () {
        it('answers 400', function (string $body) {
            $response = callApiWithRawBody(httpApp(), 'PUT', '/machines/default/service', $body);

            expect($response->getStatusCode())->toBe(400)
                ->and($response->getHeaderLine('Content-Type'))->toStartWith('application/problem+json');
        })->with([
            'not json' => ['{oops'],
            'empty body' => [''],
            'a json list' => ['[]'],
            'nothing in it' => ['{}'],
            'products missing' => ['{"coins":{}}'],
            'coins missing' => ['{"products":[]}'],
            'products is not a list' => ['{"products":{"selector":"water"},"coins":{}}'],
            'a product is not an object' => ['{"products":["water"],"coins":{}}'],
            'a product has no price' => ['{"products":[{"selector":"water","quantity":1}],"coins":{}}'],
            'price is a number, money is always text' => ['{"products":[{"selector":"water","price":0.65,"quantity":1}],"coins":{}}'],
            'quantity is text' => ['{"products":[{"selector":"water","price":"0.65","quantity":"5"}],"coins":{}}'],
            'quantity is not a whole number' => ['{"products":[{"selector":"water","price":"0.65","quantity":1.5}],"coins":{}}'],
            'selector is a number' => ['{"products":[{"selector":1,"price":"0.65","quantity":1}],"coins":{}}'],
            'coins is a list' => ['{"products":[],"coins":[1,2]}'],
            'a coin count is text' => ['{"products":[],"coins":{"0.25":"4"}}'],
        ]);

        it('answers 404 for a machine that does not exist', function (string $path) {
            $response = callApi(httpApp(), 'PUT', $path, ['products' => [], 'coins' => []]);

            expect($response->getStatusCode())->toBe(404);
        })->with([
            'unknown machine' => ['/machines/nope/service'],
            'id that can never exist' => ['/machines/bad!id/service'],
        ]);
    });
});
