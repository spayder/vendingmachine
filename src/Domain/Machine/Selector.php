<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Machine;

final readonly class Selector
{
    public const int MAX_LENGTH = 10;

    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        if (strlen($value) > self::MAX_LENGTH) {
            throw InvalidSelectorException::forValue($value);
        }

        // Groups of letters/digits separated by single hyphens. An empty group
        // (leading, trailing or doubled hyphen, or an empty string) is rejected
        // because ctype_alnum('') is false.
        foreach (explode('-', $value) as $group) {
            if (!ctype_alnum($group)) {
                throw InvalidSelectorException::forValue($value);
            }
        }

        return new self(strtoupper($value));
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
