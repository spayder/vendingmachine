<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Shared;

final readonly class Money
{
    private function __construct(private int $cents)
    {
        if ($cents < 0) {
            throw InvalidMoney::negativeAmount($cents);
        }
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function toString(): string
    {
        return sprintf('%d.%02d', intdiv($this->cents, 100), $this->cents % 100);
    }

    public function add(self $other): self
    {
        if ($other->cents > PHP_INT_MAX - $this->cents) {
            throw InvalidMoney::overflow();
        }

        return new self($this->cents + $other->cents);
    }

    public function subtract(self $other): self
    {
        $difference = $this->cents - $other->cents;

        if ($difference < 0) {
            throw InvalidMoney::negativeAmount($difference);
        }

        return new self($difference);
    }
}
