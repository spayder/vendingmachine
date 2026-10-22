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

    public static function fromString(string $amount): self
    {
        if (preg_match('/\A(\d+)(?:\.(\d{1,2}))?\z/', $amount, $matches) !== 1) {
            throw InvalidMoney::invalidFormat($amount);
        }

        $maxWhole = intdiv(PHP_INT_MAX, 100);
        $wholeDigits = ltrim($matches[1], '0');

        // Too many digits to even cast safely: reject before converting
        if (strlen($wholeDigits) > strlen((string) $maxWhole)) {
            throw InvalidMoney::overflow();
        }

        $whole = (int) $wholeDigits;                               // '' becomes 0
        $cents = (int) str_pad($matches[2] ?? '', 2, '0');         // pads on the right: '5' -> '50'

        if ($whole > $maxWhole || ($whole === $maxWhole && $cents > PHP_INT_MAX % 100)) {
            throw InvalidMoney::overflow();
        }

        return new self($whole * 100 + $cents);
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
