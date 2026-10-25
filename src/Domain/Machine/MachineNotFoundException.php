<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Machine;

final class MachineNotFoundException extends \DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function withId(MachineId $id): self
    {
        return new self('Machine not found: ' . $id->value());
    }
}
