<?php

declare(strict_types=1);

namespace VendingMachine\Infrastructure\Persistence;

use PDO;
use VendingMachine\Domain\Machine\Coin;
use VendingMachine\Domain\Machine\CoinBag;
use VendingMachine\Domain\Machine\Inventory;
use VendingMachine\Domain\Machine\MachineId;
use VendingMachine\Domain\Machine\MachineNotFoundException;
use VendingMachine\Domain\Machine\MachineRepository;
use VendingMachine\Domain\Machine\ProductSlot;
use VendingMachine\Domain\Machine\Selector;
use VendingMachine\Domain\Machine\VendingMachine;
use VendingMachine\Domain\Shared\Money;

final readonly class SqliteMachineRepository implements MachineRepository
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS machines (
            id TEXT PRIMARY KEY,
            state TEXT NOT NULL
        )',
        );
    }

    public function get(MachineId $id): VendingMachine
    {
        $statement = $this->pdo->prepare('SELECT state FROM machines WHERE id = :id');
        $statement->execute(['id' => $id->value()]);
        $state = $statement->fetchColumn();

        if ($state === false) {
            throw MachineNotFoundException::withId($id);
        }

        if (!is_string($state)) {
            throw CorruptMachineStateException::forMachine($id, 'state is not text');
        }

        try {
            return $this->rebuild($id, $state);
        } catch (\JsonException|\ValueError|\DomainException $e) {
            throw CorruptMachineStateException::forMachine($id, $e->getMessage(), $e);
        }
    }

    public function save(VendingMachine $machine): void
    {
        $state = json_encode([
            'products' => array_map(
                static fn(ProductSlot $slot): array => [
                    'selector' => $slot->selector()->value(),
                    'price' => $slot->price()->cents(),
                    'quantity' => $slot->quantity(),
                ],
                $machine->inventory()->all(),
            ),
            'availableChange' => $machine->availableChange()->counts(),
            'inserted' => $machine->inserted()->counts(),
        ], JSON_THROW_ON_ERROR);

        $this->pdo->prepare(
            'INSERT INTO machines (id, state) VALUES (:id, :state)
                    ON CONFLICT(id) DO UPDATE SET state = excluded.state',
        )->execute(['id' => $machine->id()->value(), 'state' => $state]);
    }

    private function rebuild(MachineId $id, string $json): VendingMachine
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)
            || !is_array($data['products'] ?? null)
            || !is_array($data['availableChange'] ?? null)
            || !is_array($data['inserted'] ?? null)
        ) {
            throw CorruptMachineStateException::forMachine($id, 'products, availableChange or inserted is missing');
        }

        $slots = [];
        foreach ($data['products'] as $product) {
            if (!is_array($product)
                || !is_string($product['selector'] ?? null)
                || !is_int($product['price'] ?? null)
                || !is_int($product['quantity'] ?? null)
            ) {
                throw CorruptMachineStateException::forMachine($id, 'a product is malformed');
            }

            $slots[] = ProductSlot::create(
                Selector::fromString($product['selector']),
                Money::fromCents($product['price']),
                $product['quantity'],
            );
        }

        return VendingMachine::restore(
            $id,
            Inventory::of(...$slots),
            $this->coinBag($id, $data['availableChange']),
            $this->coinBag($id, $data['inserted']),
        );
    }

    /** @param array<mixed> $counts cents => quantity */
    private function coinBag(MachineId $id, array $counts): CoinBag
    {
        $bag = CoinBag::empty();

        foreach ($counts as $cents => $quantity) {
            if (!is_int($cents) || !is_int($quantity)) {
                throw CorruptMachineStateException::forMachine($id, 'coin counts must be whole numbers');
            }

            $bag = $bag->add(Coin::from($cents), $quantity);
        }

        return $bag;
    }
}
