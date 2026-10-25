<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Machine;

use VendingMachine\Domain\Shared\Money;

final class BoundedChangeMaker implements ChangeMaker
{
    public function makeChange(Money $money, CoinBag $coinBag): ?CoinBag
    {
        $coins = $this->changeableCoinsLargestFirst();

        $counts = $this->search($money->cents(), $coins, 0, $coinBag);

        if ($counts === null) {
            return null;
        }

        $change = CoinBag::empty();

        foreach ($counts as $index => $quantity) {
            if ($quantity > 0) {
                $change = $change->add($coins[$index], $quantity);
            }
        }

        return $change;
    }

    /**
     * @return list<Coin>
     */
    private function changeableCoinsLargestFirst(): array
    {
        $coins = Coin::changeable();
        usort($coins, static fn(Coin $a, Coin $b): int => $b->value <=> $a->value);

        return $coins;
    }

    /**
     * Decides how many of $coins[$index] to use, then recurses on the next coin.
     *
     * @param list<Coin> $coins
     * @return list<int>|null quantity per coin, in the same order as $coins; null if impossible
     */
    private function search(int $cents, array $coins, int $index, CoinBag $coinBag): ?array
    {
        if ($cents === 0) {
            return array_fill(0, count($coins) - $index, 0);
        }

        if ($index === count($coins)) {
            return null;
        }

        $coin = $coins[$index];

        // Last coin type: there is only one possible answer, no need to loop.
        if ($index === count($coins) - 1) {
            $needed = intdiv($cents, $coin->value);

            if ($cents % $coin->value !== 0 || $needed > $coinBag->quantityOf($coin)) {
                return null;
            }

            return [$needed];
        }

        $most = min($coinBag->quantityOf($coin), intdiv($cents, $coin->value));

        for ($quantity = $most; $quantity >= 0; $quantity--) {
            $rest = $this->search($cents - $quantity * $coin->value, $coins, $index + 1, $coinBag);

            if ($rest !== null) {
                return [$quantity, ...$rest];
            }
        }

        return null;
    }
}
