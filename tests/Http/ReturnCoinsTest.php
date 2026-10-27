<?php

declare(strict_types=1);

describe('DELETE /machines/{id}/coins', function () {
    it('gives back the coins that were inserted (spec example 2)', function () {
        $app = httpApp();
        callApi($app, 'POST', '/machines/default/coins', ['coin' => '0.10']);
        callApi($app, 'POST', '/machines/default/coins', ['coin' => '0.10']);

        $response = callApi($app, 'DELETE', '/machines/default/coins');

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getHeaderLine('Content-Type'))->toStartWith('application/json')
            ->and(jsonBody($response))->toBe(['coins' => ['0.10', '0.10']]);
    });

    it('gives a dollar back as a dollar', function () {
        $app = httpApp();
        callApi($app, 'POST', '/machines/default/coins', ['coin' => '1']);

        $response = callApi($app, 'DELETE', '/machines/default/coins');

        expect(jsonBody($response))->toBe(['coins' => ['1.00']]);
    });

    it('lists the coins smallest first', function () {
        $app = httpApp();
        callApi($app, 'POST', '/machines/default/coins', ['coin' => '1']);
        callApi($app, 'POST', '/machines/default/coins', ['coin' => '0.05']);
        callApi($app, 'POST', '/machines/default/coins', ['coin' => '0.25']);

        $response = callApi($app, 'DELETE', '/machines/default/coins');

        expect(jsonBody($response))->toBe(['coins' => ['0.05', '0.25', '1.00']]);
    });

    it('answers with no coins when nothing was inserted', function () {
        $response = callApi(httpApp(), 'DELETE', '/machines/default/coins');

        expect($response->getStatusCode())->toBe(200)
            ->and(jsonBody($response))->toBe(['coins' => []]);
    });

    it('does not give the same coins back twice', function () {
        $app = httpApp();
        callApi($app, 'POST', '/machines/default/coins', ['coin' => '0.25']);
        callApi($app, 'DELETE', '/machines/default/coins');

        $response = callApi($app, 'DELETE', '/machines/default/coins');

        expect(jsonBody($response))->toBe(['coins' => []]);
    });

    it('lets the next customer start from nothing', function () {
        $app = httpApp();
        callApi($app, 'POST', '/machines/default/coins', ['coin' => '1']);
        callApi($app, 'DELETE', '/machines/default/coins');

        $response = callApi($app, 'POST', '/machines/default/coins', ['coin' => '0.05']);

        expect(jsonBody($response)['total'])->toBe('0.05');
    });

    it('answers 404 for a machine that does not exist', function (string $path) {
        $response = callApi(httpApp(), 'DELETE', $path);

        expect($response->getStatusCode())->toBe(404)
            ->and($response->getHeaderLine('Content-Type'))->toStartWith('application/problem+json');
    })->with([
        'unknown machine' => ['/machines/nope/coins'],
        'id that can never exist' => ['/machines/bad!id/coins'],
    ]);
});
