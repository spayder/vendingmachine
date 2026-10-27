<?php

declare(strict_types=1);

namespace VendingMachine\Infrastructure\Http\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VendingMachine\Application\GetMachineState\GetMachineStateHandler;
use VendingMachine\Application\GetMachineState\GetMachineStateQuery;
use VendingMachine\Application\ServiceMachine\ServiceMachineCommand;
use VendingMachine\Application\ServiceMachine\ServiceMachineHandler;
use VendingMachine\Infrastructure\Http\MachineStateJson;
use VendingMachine\Infrastructure\Http\MalformedRequestException;

final readonly class ServiceMachineAction
{
    public function __construct(private ServiceMachineHandler $serviceMachineHandler, private GetMachineStateHandler $getMachineStateHandler) {}

    /**
     * @param array<string, string> $args route arguments
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true);

        if (!is_array($body) || array_is_list($body)) {
            throw MalformedRequestException::because('body is not an object');
        }

        $serviceMachineCommand = new ServiceMachineCommand(
            $args['id'],
            $this->products($body['products'] ?? null),
            $this->coins($body['coins'] ?? null),
        );
        $this->serviceMachineHandler->handle($serviceMachineCommand);

        $machineStateQuery = new GetMachineStateQuery($args['id']);
        $machineState = $this->getMachineStateHandler->handle($machineStateQuery);

        $response->getBody()->write(json_encode(MachineStateJson::of($machineState), JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Checks only the shape. Whether a selector, a price or a quantity is acceptable is the domain's call.
     *
     * @return list<array{selector: string, price: string, quantity: int}>
     */
    private function products(mixed $products): array
    {
        if (!is_array($products) || !array_is_list($products)) {
            throw MalformedRequestException::because('"products" must be a list');
        }

        $rows = [];
        foreach ($products as $product) {
            if (!is_array($product)) {
                throw MalformedRequestException::because('every product must be an object');
            }

            $selector = $product['selector'] ?? null;
            $price = $product['price'] ?? null;
            $quantity = $product['quantity'] ?? null;

            if (!is_string($selector) || !is_string($price) || !is_int($quantity)) {
                throw MalformedRequestException::because(
                    'every product needs a text "selector", a text "price" and a whole number "quantity"',
                );
            }

            $rows[] = ['selector' => $selector, 'price' => $price, 'quantity' => $quantity];
        }

        return $rows;
    }

    /**
     * @return array<int|string, int>
     */
    private function coins(mixed $coins): array
    {
        // An empty object and an empty list are the same thing to PHP, so an empty one is fine.
        if (!is_array($coins) || ($coins !== [] && array_is_list($coins))) {
            throw MalformedRequestException::because('"coins" must be an object of coin => count');
        }

        $counts = [];
        foreach ($coins as $coin => $count) {
            if (!is_int($count)) {
                throw MalformedRequestException::because('every coin count must be a whole number');
            }

            $counts[$coin] = $count;
        }

        return $counts;
    }
}
