<?php

declare(strict_types=1);

namespace VendingMachine\Infrastructure\Http\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VendingMachine\Application\Purchase\PurchaseCommand;
use VendingMachine\Application\Purchase\PurchaseHandler;
use VendingMachine\Infrastructure\Http\CoinList;
use VendingMachine\Infrastructure\Http\MalformedRequestException;

final readonly class PurchaseAction
{
    public function __construct(private PurchaseHandler $purchaseHandler) {}

    /**
     * @param array<string, string> $args route arguments
     * @throws \JsonException
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true);

        if (!is_array($body) || array_is_list($body)) {
            throw MalformedRequestException::because('body is not an object');
        }

        $selector = $body['selector'] ?? null;

        if (!is_string($selector)) {
            throw MalformedRequestException::because('"selector" must be a string');
        }

        $command = new PurchaseCommand($args['id'], $selector);
        $purchase = $this->purchaseHandler->handle($command);

        $result = [
            'item' => $purchase->selector()->value(),
            'change' => CoinList::of($purchase->change()),
        ];

        $response->getBody()->write(json_encode($result, JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
