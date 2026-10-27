<?php

declare(strict_types=1);

namespace VendingMachine\Infrastructure\Http\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VendingMachine\Application\GetMachineState\GetMachineStateHandler;
use VendingMachine\Application\GetMachineState\GetMachineStateQuery;
use VendingMachine\Infrastructure\Http\MachineStateJson;

final readonly class GetMachineStateAction
{
    public function __construct(private GetMachineStateHandler $getMachineStateHandler) {}

    /**
     * @param array<string, string> $args route arguments
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $machineStateQuery = new GetMachineStateQuery($args['id']);
        $machineState = $this->getMachineStateHandler->handle($machineStateQuery);

        $response->getBody()->write(json_encode(MachineStateJson::of($machineState), JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
