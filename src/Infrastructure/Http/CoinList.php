<?php

declare(strict_types=1);

namespace VendingMachine\Infrastructure\Http;

use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Machine\CoinBag;

/**
 * How coins look in a response: one entry per coin, smallest first, as money text.
 * A bag of two dimes and a dollar becomes ["0.10", "0.10", "1.00"].
 */
final class CoinList
{
    /**
     * @return list<string>
     */
    public static function of(CoinBag $coins): array
    {
        $counts = $coins->counts();
        ksort($counts);

        $list = [];
        foreach ($counts as $cents => $quantity) {
            $text = Coin::from($cents)->toMoney()->toString();

            for ($i = 0; $i < $quantity; $i++) {
                $list[] = $text;
            }
        }

        return $list;
    }
}
