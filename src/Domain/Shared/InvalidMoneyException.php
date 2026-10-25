<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Shared;

final class InvalidMoneyException extends \DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function negativeAmount(int $cents): self
    {
        return new self("Money cannot be negative, got {$cents} cents");
    }

    public static function overflow(): self
    {
        return new self('Money overflow');
    }

    public static function invalidFormat(string $amount): self
    {
        return new self("Invalid money amount: {$amount}");
    }
}
