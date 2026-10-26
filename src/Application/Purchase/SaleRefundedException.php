<?php

declare(strict_types=1);

namespace VendingMachine\Application\Purchase;

use VendingMachine\Domain\Machine\CannotMakeChangeException;
use VendingMachine\Domain\Machine\CoinBag;

/**
 * The sale was refused because exact change was not available, and the customer's coins were given back.
 */
final class SaleRefundedException extends \DomainException
{
    private function __construct(
        string $message,
        private readonly CoinBag $refund,
        CannotMakeChangeException $cause,
    ) {
        parent::__construct($message, 0, $cause);
    }

    public static function because(CannotMakeChangeException $cause, CoinBag $refund): self
    {
        return new self(
            $cause->getMessage() . '; ' . $refund->total()->toString() . ' refunded',
            $refund,
            $cause,
        );
    }

    public function refund(): CoinBag
    {
        return $this->refund;
    }
}
