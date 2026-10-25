<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Machine;

final readonly class MachineId
{
    public const int MAX_LENGTH = 64;

    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        // Hyphens and underscores are allowed separators; what remains must be letters/digits.
        $withoutSeparators = str_replace(['-', '_'], '', $value);

        if (strlen($value) > self::MAX_LENGTH || !ctype_alnum($withoutSeparators)) {
            throw InvalidMachineId::forValue($value);
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
