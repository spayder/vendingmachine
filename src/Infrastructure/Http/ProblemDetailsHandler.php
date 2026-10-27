<?php

declare(strict_types=1);

namespace VendingMachine\Infrastructure\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use VendingMachine\Application\Purchase\SaleRefundedException;
use VendingMachine\Domain\Machine\CannotMakeChangeException;
use VendingMachine\Domain\Machine\DuplicateProductException;
use VendingMachine\Domain\Machine\InsufficientFundsException;
use VendingMachine\Domain\Machine\InvalidCoinQuantityException;
use VendingMachine\Domain\Machine\InvalidMachineIdException;
use VendingMachine\Domain\Machine\InvalidSelectorException;
use VendingMachine\Domain\Machine\InvalidStockException;
use VendingMachine\Domain\Machine\MachineNotFoundException;
use VendingMachine\Domain\Machine\OutOfStockException;
use VendingMachine\Domain\Machine\SessionInProgressException;
use VendingMachine\Domain\Machine\UnknownProductException;
use VendingMachine\Domain\Machine\UnsupportedCoinException;
use VendingMachine\Domain\Shared\InvalidMoneyException;

/**
 * Turns any exception into an RFC 7807 problem document (application/problem+json).
 * This table is the only place that knows which failure means which HTTP status.
 */
final readonly class ProblemDetailsHandler
{
    private const int INTERNAL_ERROR = 500;

    /** @var array<class-string<\Throwable>, int> */
    private const array STATUS_BY_EXCEPTION = [
        MalformedRequestException::class => 400,

        HttpNotFoundException::class => 404,
        MachineNotFoundException::class => 404,
        InvalidMachineIdException::class => 404,
        UnknownProductException::class => 404,

        HttpMethodNotAllowedException::class => 405,

        OutOfStockException::class => 409,
        InsufficientFundsException::class => 409,
        SessionInProgressException::class => 409,
        CannotMakeChangeException::class => 409,
        SaleRefundedException::class => 409,

        UnsupportedCoinException::class => 422,
        InvalidMoneyException::class => 422,
        InvalidSelectorException::class => 422,
        DuplicateProductException::class => 422,
        InvalidStockException::class => 422,
        InvalidCoinQuantityException::class => 422,
    ];

    /** @var array<int, string> */
    private const array TITLES = [
        400 => 'Bad Request',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        422 => 'Unprocessable Content',
        500 => 'Internal Server Error',
    ];

    public function __construct(private ResponseFactoryInterface $responseFactory) {}

    public function __invoke(
        ServerRequestInterface $request,
        \Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
    ): ResponseInterface {
        $status = $this->statusOf($exception);

        $problem = [
            'type' => 'about:blank',
            'title' => self::TITLES[$status],
            'status' => $status,
            // An unexpected failure must not leak internals (paths, SQL, stack traces).
            'detail' => $status === self::INTERNAL_ERROR ? 'The server failed to process the request.' : $exception->getMessage(),
        ];

        if ($exception instanceof SaleRefundedException) {
            $problem['refund'] = CoinList::of($exception->refund());
        }

        $response = $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/problem+json');

        if ($exception instanceof HttpMethodNotAllowedException) {
            $response = $response->withHeader('Allow', implode(', ', $exception->getAllowedMethods()));
        }

        $response->getBody()->write(json_encode($problem, JSON_THROW_ON_ERROR));

        return $response;
    }

    private function statusOf(\Throwable $exception): int
    {
        foreach (self::STATUS_BY_EXCEPTION as $class => $status) {
            if ($exception instanceof $class) {
                return $status;
            }
        }

        return self::INTERNAL_ERROR;
    }
}
