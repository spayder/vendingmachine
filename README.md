# Vending Machine

A vending machine modelled as a small HTTP service in PHP 8.5.
Work in progress: the domain, application and persistence layers are done; the HTTP API comes next.

## Requirements

- Docker with Compose v2
- `make` (optional; every target is just a shortcut for the `docker compose` command shown next to it)

## Available commands

```bash
make help        # list all targets with a short description
```

Plain `make` does the same, because `help` is the default goal.

## Run

```bash
make up          # docker compose up --build -d
```

The service listens on http://localhost:8080. Stop it with `make down`
(`docker compose down`).

## Test

```bash
make test        # docker compose run --rm dev vendor/bin/pest
make coverage    # same, with --coverage
make ci          # code style, PHPStan (level max) and tests: what the CI pipeline runs
```

## The machine

| | |
|---|---|
| Coins accepted | 0.05, 0.10, 0.25, 1.00 |
| Products | Water 0.65, Juice 1.00, Soda 1.50 (price, selector and count are data, not code) |
| Customer actions | insert a coin, RETURN-COIN, buy a product |
| Operator action | SERVICE: set the stock and the coins the machine holds |
| State | the stock, the change it holds, and the money currently inserted |

### Rules and assumptions

The task leaves some points open. These are the choices made, and why.

- **Money is an integer number of cents.** Floats cannot represent 0.05 + 0.10 exactly. Amounts are strings in
  the API (`"0.25"`), and floats are never accepted.
- **RETURN-COIN gives back the exact coins that were inserted**, so a 1.00 comes back as a 1.00.
- **The machine never gives a 1.00 coin as change.** It accepts them, keeps them, and counts them in the
  state, but change is made from 0.05, 0.10 and 0.25 only.
- **Coins inserted in the current purchase count as available change.** With a price of 0.65 and 0.85 inserted
  as 2 quarters, 2 dimes and a nickel, the 0.20 change can only be paid with the customer's own dimes.
- **If exact change cannot be made, the sale is refused and the inserted coins are returned.** The response says
  so (see `SaleRefundedException`). The alternative, keeping the money in the session so the customer can add
  exact coins, was rejected because the customer would be stuck with a machine that cannot finish the sale.
- **Not enough money or sold out**: the sale is refused and the money stays inserted, so the customer can add
  coins or take them back.
- **SERVICE replaces** the stock and the change (it is idempotent, and matches "set the available change and
  item counts"). It is **rejected while a customer has money inserted**, so an operator cannot wipe a
  customer's coins.
- **Selectors** are case-insensitive and stored in upper case: up to 10 letters and digits, with single hyphens
  between groups. A machine id is up to 64 letters, digits, `-` and `_`.
- There can be several machines. The routes will use `/machines/{id}`, and a `default` machine is seeded.

## Design

Hexagonal architecture with a small domain model. Dependencies point inward only.

```
src/
  Domain/            no framework, no I/O
    Shared/          Money
    Machine/         VendingMachine (aggregate root), Coin, CoinBag, Inventory, ProductSlot, Selector,
                     MachineId, ChangeMaker + BoundedChangeMaker, PurchaseResult, MachineRepository (port),
                     and the domain exceptions
  Application/       one folder per use case: a Command (raw input) and a Handler
                     InsertCoin, ReturnCoin, Purchase, ServiceMachine, GetMachineState (query)
  Infrastructure/
    Persistence/     SqliteMachineRepository (adapter for the port)
```

- **The aggregate protects the rules.** `VendingMachine` checks everything first and changes state last, so a
  method that throws leaves the machine exactly as it was. Handlers load, call, save, and decide nothing.
- **Commands carry strings, the handler builds domain objects.** Every validation error therefore comes from
  the domain, in one place, instead of being repeated in a controller.
- **The refund policy lives in `PurchaseHandler`**, not in the aggregate. The aggregate only says "I cannot
  make change"; what to do about it (refund and report) is an application decision.
- **Queries return a read model.** `GetMachineState` returns plain strings and ints (`MachineState`), so the
  HTTP layer never touches the mutable aggregate.
- **Change is made by `BoundedChangeMaker`**, behind the `ChangeMaker` interface. It is a backtracking search
  over the coins that are actually in stock, trying large coins first. A greedy algorithm is wrong with a
  limited stock: owed 0.30 with one quarter and three dimes, greedy takes the quarter and then gets stuck,
  while three dimes work.
- **Persistence is a JSON snapshot per machine** in one SQLite row (`machines(id, state)`). We only ever load
  and save a whole machine by id and never query inside one, so a snapshot is the simplest fit, and one save is
  one atomic statement. Damaged data raises `CorruptMachineStateException`, not a half-built machine. The
  tests use the same contract suite against an in-memory repository and the SQLite one, so they cannot drift.
  On a larger system the same document would sit in a `jsonb` column.
- **Concurrency: last write wins.** Two simultaneous requests to the same machine could overwrite each other.
  The fix for production is a `version` column with a compare-and-swap on save, mapped to a 409 and a retry.
  It was left out deliberately to keep the take-home focused, and the repository port would not change.

## Tests

- Unit tests for every value object and for the aggregate, table-driven where it helps (change making,
  money parsing).
- Application tests drive each handler against an in-memory repository.
- A contract test runs against every repository implementation.
- HTTP and acceptance tests will cover the three scenarios from the task once the API exists.

## Status

Done: Docker and tooling (nginx, php-fpm, Pest, PHPStan max, PHP-CS-Fixer, CI), the domain, the five use
cases, and the SQLite repository.
Next: the HTTP API (Slim 4), the seeded `default` machine, API reference and examples.
