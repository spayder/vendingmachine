<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Machine;

final class InvalidSelector extends \DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forValue(string $value): self
    {
        return new self('Invalid selector for ' . $value);
    }
}
