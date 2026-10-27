<?php

declare(strict_types=1);

namespace VendingMachine\Infrastructure\Http\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VendingMachine\Application\InsertCoin\InsertCoinCommand;
use VendingMachine\Application\InsertCoin\InsertCoinHandler;
use VendingMachine\Infrastructure\Http\CoinList;
use VendingMachine\Infrastructure\Http\MalformedRequestException;

final readonly class InsertCoinAction
{
    public function __construct(private InsertCoinHandler $insertCoinHandler) {}

    /**
     * @param array<string, string> $args route arguments
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true);

        if (!is_array($body) || array_is_list($body)) {
            throw MalformedRequestException::because('body is not an object');
        }

        $coin = $body['coin'] ?? null;

        if (!is_string($coin)) {
            throw MalformedRequestException::because('"coin" must be a string');
        }

        $command = new InsertCoinCommand($args['id'], $coin);
        $inserted = $this->insertCoinHandler->handle($command);

        $result = [
            'inserted' => CoinList::of($inserted),
            'total' => $inserted->total()->toString(),
        ];
        $response->getBody()->write(json_encode($result, JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
