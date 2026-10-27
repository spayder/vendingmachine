<?php

declare(strict_types=1);

namespace VendingMachine\Infrastructure\Http\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VendingMachine\Application\ReturnCoin\ReturnCoinCommand;
use VendingMachine\Application\ReturnCoin\ReturnCoinHandler;
use VendingMachine\Infrastructure\Http\CoinList;

final readonly class ReturnCoinsAction
{
    public function __construct(private ReturnCoinHandler $returnCoinHandler) {}

    /**
     * @param array<string, string> $args route arguments
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $command = new ReturnCoinCommand($args['id']);
        $returned = $this->returnCoinHandler->handle($command);

        $response->getBody()->write(json_encode(['coins' => CoinList::of($returned)], JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
