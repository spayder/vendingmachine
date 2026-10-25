<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Machine;

final class DuplicateProductException extends \DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forSelector(Selector $selector): self
    {
        return new self('Duplicate product: ' . $selector->value());
    }
}
