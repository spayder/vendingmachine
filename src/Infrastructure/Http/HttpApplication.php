<?php

declare(strict_types=1);

namespace VendingMachine\Infrastructure\Http;

use DI\Container;
use PDO;
use Slim\App;
use Slim\Factory\AppFactory;
use VendingMachine\Domain\Machine\BoundedChangeMaker;
use VendingMachine\Domain\Machine\ChangeMaker;
use VendingMachine\Domain\Machine\MachineRepository;
use VendingMachine\Infrastructure\Http\Action\GetMachineStateAction;
use VendingMachine\Infrastructure\Http\Action\InsertCoinAction;
use VendingMachine\Infrastructure\Http\Action\PurchaseAction;
use VendingMachine\Infrastructure\Http\Action\ReturnCoinsAction;
use VendingMachine\Infrastructure\Persistence\SqliteMachineRepository;

final class HttpApplication
{
    /**
     * @return App<Container>
     */
    public static function create(PDO $pdo): App
    {
        $container = new Container();
        $container->set(MachineRepository::class, new SqliteMachineRepository($pdo));
        $container->set(ChangeMaker::class, new BoundedChangeMaker());
        $app = AppFactory::createFromContainer($container);

        $app->post('/machines/{id}/coins', InsertCoinAction::class);
        $app->delete('/machines/{id}/coins', ReturnCoinsAction::class);

        $app->post('/machines/{id}/purchases', PurchaseAction::class);

        $app->get('/machines/{id}', GetMachineStateAction::class);

        $app->addRoutingMiddleware();
        $errors = $app->addErrorMiddleware(false, false, false);
        $errors->setDefaultErrorHandler(new ProblemDetailsHandler($app->getResponseFactory()));

        return $app;
    }
}
