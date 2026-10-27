<?php

declare(strict_types=1);

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use DI\Container;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use VendingMachine\Domain\Machine\VendingMachine;
use VendingMachine\Infrastructure\Http\HttpApplication;
use VendingMachine\Infrastructure\Persistence\SqliteMachineRepository;

/**
 * The whole application on an in-memory database, holding one machine (the spec's by default).
 *
 * @return App<Container>
 */
function httpApp(?VendingMachine $machine = null): App
{
    $pdo = new PDO('sqlite::memory:');
    new SqliteMachineRepository($pdo)->save($machine ?? machine());

    return HttpApplication::create($pdo);
}

/**
 * @param App<Container> $app
 * @param array<string, mixed>|null $json
 */
function callApi(App $app, string $method, string $uri, ?array $json = null): ResponseInterface
{
    $request = new ServerRequest($method, $uri);

    if ($json !== null) {
        $request = $request
            ->withHeader('Content-Type', 'application/json')
            ->withBody(Stream::create(json_encode($json, JSON_THROW_ON_ERROR)));
    }

    return $app->handle($request);
}

/**
 * @param App<Container> $app
 */
function callApiWithRawBody(App $app, string $method, string $uri, string $body): ResponseInterface
{
    return $app->handle(
        new ServerRequest($method, $uri, ['Content-Type' => 'application/json'], Stream::create($body)),
    );
}

/**
 * @return array<string, mixed>
 */
function jsonBody(ResponseInterface $response): array
{
    $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    assert(is_array($decoded));

    /** @var array<string, mixed> $decoded */
    return $decoded;
}
