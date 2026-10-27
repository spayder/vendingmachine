<?php

declare(strict_types=1);

use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Machine\CoinBag;
use VendingMachine\Domain\Machine\Inventory;
use VendingMachine\Domain\Machine\MachineId;
use VendingMachine\Domain\Machine\MachineNotFoundException;
use VendingMachine\Domain\Machine\ProductSlot;
use VendingMachine\Domain\Machine\Selector;
use VendingMachine\Domain\Machine\VendingMachine;
use VendingMachine\Domain\Shared\Money;
use VendingMachine\Infrastructure\Persistence\SqliteMachineRepository;

require __DIR__ . '/../vendor/autoload.php';

/*
 * Creates the "default" machine the first time the service starts.
 * It never touches a machine that already exists, so a restart keeps the stock and the coins as they are.
 */
$databasePath = getenv('DATABASE_PATH') ?: __DIR__ . '/../var/machines.sqlite';

if (!is_dir(dirname($databasePath))) {
    mkdir(dirname($databasePath), 0o775, true);
}

$repository = new SqliteMachineRepository(new PDO('sqlite:' . $databasePath));
$id = MachineId::fromString('default');

try {
    $repository->get($id);
    echo "Machine 'default' already exists, leaving it as it is.\n";

    return;
} catch (MachineNotFoundException) {
    // First start: fall through and create it.
}

$slot = static fn(string $selector, string $price): ProductSlot => ProductSlot::create(
    Selector::fromString($selector),
    Money::fromString($price),
    10,
);

$repository->save(VendingMachine::create(
    $id,
    Inventory::of($slot('water', '0.65'), $slot('juice', '1.00'), $slot('soda', '1.50')),
    CoinBag::empty()->add(Coin::Nickel, 10)->add(Coin::Dime, 10)->add(Coin::Quarter, 10),
));

echo "Machine 'default' created: Water 0.65, Juice 1.00 and Soda 1.50, ten of each, and ten of every change coin.\n";
