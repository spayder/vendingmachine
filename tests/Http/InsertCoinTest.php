<?php

declare(strict_types=1);

describe('POST /machines/{id}/coins', function () {
    it('accepts a coin and says what is inserted', function () {
        $response = callApi(httpApp(), 'POST', '/machines/default/coins', ['coin' => '0.25']);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getHeaderLine('Content-Type'))->toStartWith('application/json')
            ->and(jsonBody($response))->toBe(['inserted' => ['0.25'], 'total' => '0.25']);
    });

    it('adds up the coins of one customer, smallest first', function () {
        $app = httpApp();

        callApi($app, 'POST', '/machines/default/coins', ['coin' => '1']);
        callApi($app, 'POST', '/machines/default/coins', ['coin' => '0.10']);
        $response = callApi($app, 'POST', '/machines/default/coins', ['coin' => '0.10']);

        expect(jsonBody($response))->toBe(['inserted' => ['0.10', '0.10', '1.00'], 'total' => '1.20']);
    });

    it('keeps machines apart', function () {
        $app = httpApp();

        $response = callApi($app, 'POST', '/machines/default/coins', ['coin' => '0.05']);

        expect(jsonBody($response)['total'])->toBe('0.05');
    });

    describe('when the request is well formed but the value is not acceptable', function () {
        it('answers 422 with a problem document', function (string $coin) {
            $response = callApi(httpApp(), 'POST', '/machines/default/coins', ['coin' => $coin]);

            expect($response->getStatusCode())->toBe(422)
                ->and($response->getHeaderLine('Content-Type'))->toStartWith('application/problem+json')
                ->and(jsonBody($response))->toMatchArray(['status' => 422, 'title' => 'Unprocessable Content']);
        })->with([
            'a coin that does not exist' => ['0.02'],
            'two dollars' => ['2'],
            'not a number' => ['abc'],
            'empty' => [''],
        ]);

        it('explains what is wrong', function () {
            $response = callApi(httpApp(), 'POST', '/machines/default/coins', ['coin' => '0.02']);

            expect(jsonBody($response)['detail'])->toBeString();
        });
    });

    describe('when the request is malformed', function () {
        it('answers 400', function (string $body) {
            $response = callApiWithRawBody(httpApp(), 'POST', '/machines/default/coins', $body);

            expect($response->getStatusCode())->toBe(400)
                ->and($response->getHeaderLine('Content-Type'))->toStartWith('application/problem+json')
                ->and(jsonBody($response)['status'])->toBe(400);
        })->with([
            'not json' => ['{oops'],
            'empty body' => [''],
            'a json list' => ['[]'],
            'coin missing' => ['{}'],
            'coin is a number, money is always text' => ['{"coin":0.25}'],
            'coin is null' => ['{"coin":null}'],
        ]);
    });

    describe('when the machine cannot be found', function () {
        it('answers 404', function (string $path) {
            $response = callApi(httpApp(), 'POST', $path, ['coin' => '0.25']);

            expect($response->getStatusCode())->toBe(404)
                ->and($response->getHeaderLine('Content-Type'))->toStartWith('application/problem+json');
        })->with([
            'unknown machine' => ['/machines/nope/coins'],
            'id that can never exist' => ['/machines/bad!id/coins'],
        ]);
    });
});

describe('routing', function () {
    it('answers 404 as a problem document for a route that does not exist', function () {
        $response = callApi(httpApp(), 'GET', '/nothing-here');

        expect($response->getStatusCode())->toBe(404)
            ->and($response->getHeaderLine('Content-Type'))->toStartWith('application/problem+json')
            ->and(jsonBody($response)['status'])->toBe(404);
    });

    it('answers 405 as a problem document for a method the route does not offer', function () {
        $response = callApi(httpApp(), 'PUT', '/machines/default/coins', ['coin' => '0.25']);

        expect($response->getStatusCode())->toBe(405)
            ->and($response->getHeaderLine('Content-Type'))->toStartWith('application/problem+json');
    });
});
