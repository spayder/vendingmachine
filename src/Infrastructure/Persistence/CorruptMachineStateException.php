<?php

declare(strict_types=1);

namespace VendingMachine\Infrastructure\Persistence;

use VendingMachine\Domain\Machine\MachineId;

/**
 * The stored state of a machine cannot be turned back into a machine: something other than this code wrote it.
 */
final class CorruptMachineStateException extends \RuntimeException
{
    private function __construct(string $message, ?\Throwable $previous)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function forMachine(MachineId $id, string $reason, ?\Throwable $previous = null): self
    {
        return new self('Stored state of machine ' . $id->value() . ' is corrupt: ' . $reason, $previous);
    }
}
