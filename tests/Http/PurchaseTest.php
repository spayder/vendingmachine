<?php

declare(strict_types=1);

use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Machine\CoinBag;
use VendingMachine\Domain\Machine\Inventory;

describe('POST /machines/{id}/purchases', function () {
    describe('when the sale goes through', function () {
        it('vends the item for exact money (spec example 1)', function () {
            $app = httpApp();
            foreach (['1', '0.25', '0.25'] as $coin) {
                callApi($app, 'POST', '/machines/default/coins', ['coin' => $coin]);
            }

            $response = callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'soda']);

            expect($response->getStatusCode())->toBe(200)
                ->and($response->getHeaderLine('Content-Type'))->toStartWith('application/json')
                ->and(jsonBody($response))->toBe(['item' => 'SODA', 'change' => []]);
        });

        it('vends the item and returns the change (spec example 3)', function () {
            $app = httpApp(machine(CoinBag::empty()->add(Coin::Quarter, 2)->add(Coin::Dime, 2)));
            callApi($app, 'POST', '/machines/default/coins', ['coin' => '1']);

            $response = callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'water']);

            expect($response->getStatusCode())->toBe(200)
                ->and(jsonBody($response))->toBe(['item' => 'WATER', 'change' => ['0.10', '0.25']]);
        });

        it('does not care about the case of the selector', function () {
            $app = httpApp();
            callApi($app, 'POST', '/machines/default/coins', ['coin' => '1']);

            $response = callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'Juice']);

            expect(jsonBody($response)['item'])->toBe('JUICE');
        });

        it('empties the customer\'s credit', function () {
            $app = httpApp();
            foreach (['1', '0.25', '0.25'] as $coin) {
                callApi($app, 'POST', '/machines/default/coins', ['coin' => $coin]);
            }
            callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'soda']);

            $response = callApi($app, 'DELETE', '/machines/default/coins');

            expect(jsonBody($response))->toBe(['coins' => []]);
        });

        it('uses up the stock', function () {
            $app = httpApp(machine(CoinBag::empty(), Inventory::of(slotOf('water', 65, 1))));
            foreach (['0.25', '0.25', '0.10', '0.05'] as $coin) {
                callApi($app, 'POST', '/machines/default/coins', ['coin' => $coin]);
            }
            callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'water']);
            foreach (['0.25', '0.25', '0.10', '0.05'] as $coin) {
                callApi($app, 'POST', '/machines/default/coins', ['coin' => $coin]);
            }

            $response = callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'water']);

            expect($response->getStatusCode())->toBe(409);
        });
    });

    describe('when the sale is refused and the money stays inserted', function () {
        it('answers 409 when the customer has not put in enough', function () {
            $app = httpApp();
            callApi($app, 'POST', '/machines/default/coins', ['coin' => '0.25']);

            $response = callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'water']);

            expect($response->getStatusCode())->toBe(409)
                ->and($response->getHeaderLine('Content-Type'))->toStartWith('application/problem+json')
                ->and(jsonBody($response))->toMatchArray(['status' => 409, 'title' => 'Conflict']);
            expect(jsonBody(callApi($app, 'DELETE', '/machines/default/coins')))->toBe(['coins' => ['0.25']]);
        });

        it('answers 409 when the product is sold out', function () {
            $app = httpApp(machine(CoinBag::empty(), Inventory::of(slotOf('water', 65, 0))));
            callApi($app, 'POST', '/machines/default/coins', ['coin' => '1']);

            $response = callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'water']);

            expect($response->getStatusCode())->toBe(409);
            expect(jsonBody(callApi($app, 'DELETE', '/machines/default/coins')))->toBe(['coins' => ['1.00']]);
        });

        it('answers 404 for a product the machine does not sell', function () {
            $app = httpApp();
            callApi($app, 'POST', '/machines/default/coins', ['coin' => '1']);

            $response = callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'tea']);

            expect($response->getStatusCode())->toBe(404);
            expect(jsonBody(callApi($app, 'DELETE', '/machines/default/coins')))->toBe(['coins' => ['1.00']]);
        });
    });

    describe('when exact change cannot be given', function () {
        it('answers 409 and says which coins were given back', function () {
            $app = httpApp(machine(CoinBag::empty()));
            callApi($app, 'POST', '/machines/default/coins', ['coin' => '1']);

            $response = callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'water']);

            expect($response->getStatusCode())->toBe(409)
                ->and($response->getHeaderLine('Content-Type'))->toStartWith('application/problem+json')
                ->and(jsonBody($response))->toMatchArray(['status' => 409, 'refund' => ['1.00']]);
        });

        it('really gives the coins back', function () {
            $app = httpApp(machine(CoinBag::empty()));
            callApi($app, 'POST', '/machines/default/coins', ['coin' => '1']);
            callApi($app, 'POST', '/machines/default/purchases', ['selector' => 'water']);

            $response = callApi($app, 'DELETE', '/machines/default/coins');

            expect(jsonBody($response))->toBe(['coins' => []]);
        });
    });

    describe('when the request is not acceptable', function () {
        it('answers 422 for a selector that can never be valid', function () {
            $response = callApi(httpApp(), 'POST', '/machines/default/purchases', ['selector' => 'not valid!']);

            expect($response->getStatusCode())->toBe(422)
                ->and($response->getHeaderLine('Content-Type'))->toStartWith('application/problem+json');
        });

        it('answers 400 when the body is malformed', function (string $body) {
            $response = callApiWithRawBody(httpApp(), 'POST', '/machines/default/purchases', $body);

            expect($response->getStatusCode())->toBe(400)
                ->and($response->getHeaderLine('Content-Type'))->toStartWith('application/problem+json');
        })->with([
            'not json' => ['{oops'],
            'empty body' => [''],
            'a json list' => ['[]'],
            'selector missing' => ['{}'],
            'selector is a number' => ['{"selector":1}'],
            'selector is null' => ['{"selector":null}'],
        ]);

        it('answers 404 for a machine that does not exist', function (string $path) {
            $response = callApi(httpApp(), 'POST', $path, ['selector' => 'water']);

            expect($response->getStatusCode())->toBe(404);
        })->with([
            'unknown machine' => ['/machines/nope/purchases'],
            'id that can never exist' => ['/machines/bad!id/purchases'],
        ]);
    });
});
