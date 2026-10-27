# Vending Machine

A vending machine modelled as a small HTTP service in PHP 8.5: hexagonal architecture, a rich domain model,
SQLite persistence, and a test suite of about 440 tests that CI runs on every push.

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

On the first start the container creates a machine called `default` (Water 0.65, Juice 1.00 and Soda 1.50, ten of
each, and ten nickels, dimes and quarters of change). The machine lives in a SQLite file on the named volume
`machine-data`, so it survives restarts and `make down`. A restart never overwrites it. To start again from the
seed, delete the volume with `docker compose down -v`.

## Test

The test tools are development dependencies, so install them once after cloning (it runs `composer install` in the
`dev` container, nothing is needed on the host):

```bash
make install     # docker compose run --rm dev composer install
```

Then:

```bash
make test        # docker compose run --rm dev vendor/bin/pest
make coverage    # same, with --coverage
make ci          # code style, PHPStan (level max) and tests: what the CI pipeline runs
```

## API

All bodies are JSON. Money is always a **string** (`"0.25"`), never a number. Replace `default` with any machine id.

| Spec action | Request | Success |
|---|---|---|
| insert 0.05, 0.10, 0.25 or 1 | `POST /machines/{id}/coins` `{"coin":"0.25"}` | `200` `{"inserted":["0.25"],"total":"0.25"}` |
| RETURN-COIN | `DELETE /machines/{id}/coins` | `200` `{"coins":["0.10","0.10"]}` |
| GET-WATER, GET-JUICE, GET-SODA | `POST /machines/{id}/purchases` `{"selector":"water"}` | `200` `{"item":"WATER","change":["0.10","0.25"]}` |
| SERVICE | `PUT /machines/{id}/service` `{"products":[...],"coins":{...}}` | `200` the new state |
| state | `GET /machines/{id}` | `200` the state |

Coin lists name every coin, smallest first. A selector is case-insensitive and is answered in upper case.

### The three scenarios from the task

These are real calls and real responses against the seeded `default` machine.

**1. Buy a soda with exact money** (1.00 + 0.25 + 0.25)

```bash
curl -s -X POST localhost:8080/machines/default/coins -H 'Content-Type: application/json' -d '{"coin":"1"}'
curl -s -X POST localhost:8080/machines/default/coins -H 'Content-Type: application/json' -d '{"coin":"0.25"}'
curl -s -X POST localhost:8080/machines/default/coins -H 'Content-Type: application/json' -d '{"coin":"0.25"}'
curl -s -X POST localhost:8080/machines/default/purchases -H 'Content-Type: application/json' -d '{"selector":"soda"}'
```
```json
{"item":"SODA","change":[]}
```

**2. Insert two dimes and take them back**

```bash
curl -s -X POST localhost:8080/machines/default/coins -H 'Content-Type: application/json' -d '{"coin":"0.10"}'
curl -s -X POST localhost:8080/machines/default/coins -H 'Content-Type: application/json' -d '{"coin":"0.10"}'
curl -s -X DELETE localhost:8080/machines/default/coins
```
```json
{"coins":["0.10","0.10"]}
```

**3. Pay 1.00 for a water (0.65) and get 0.35 of change**

```bash
curl -s -X POST localhost:8080/machines/default/coins -H 'Content-Type: application/json' -d '{"coin":"1"}'
curl -s -X POST localhost:8080/machines/default/purchases -H 'Content-Type: application/json' -d '{"selector":"water"}'
```
```json
{"item":"WATER","change":["0.10","0.25"]}
```

### Service

SERVICE **replaces** the stock and the coins the machine holds. Both fields are required, and an empty list or object
empties that part of the machine. A dollar can be written `"1"` or `"1.00"`.

```bash
curl -s -X PUT localhost:8080/machines/default/service -H 'Content-Type: application/json' \
  -d '{"products":[{"selector":"water","price":"0.65","quantity":5}],"coins":{"0.25":4,"0.10":10}}'
```

The answer is the machine state, the same document as `GET /machines/default`:

```json
{
  "products": [{"selector": "WATER", "price": "0.65", "quantity": 5}],
  "availableChange": {"0.05": 0, "0.10": 10, "0.25": 4, "1.00": 0},
  "inserted": {"coins": {"0.05": 0, "0.10": 0, "0.25": 0, "1.00": 0}, "total": "0.00"}
}
```

### Errors

Every error is an [RFC 7807](https://www.rfc-editor.org/rfc/rfc7807) document, `application/problem+json`:

```json
{"type":"about:blank","title":"Conflict","status":409,"detail":"Insufficient funds for WATER: 0.65 needed, 0.25 inserted"}
```

| Status | Meaning |
|---|---|
| `400` | the body is not JSON, a field is missing, or a field has the wrong JSON type (for example a coin sent as the number `0.25`) |
| `404` | unknown machine, product or route |
| `405` | the route does not offer that method (the `Allow` header lists the ones it does) |
| `409` | the request is fine but the machine cannot do it now: not enough money, sold out, a customer is mid-purchase during SERVICE, or exact change cannot be made |
| `422` | the request is well formed but a value is not acceptable: a coin that does not exist, a bad amount, an invalid or duplicated selector, negative stock |
| `500` | anything unexpected, with a fixed message so that internals never leak |

When exact change cannot be made, the sale is refused and the coins are returned in the same answer:

```json
{"type":"about:blank","title":"Conflict","status":409,"detail":"Cannot make change: 0.35 owed; 1.00 refunded","refund":["1.00"]}
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
  as 2 quarters, 3 dimes and a nickel, the 0.20 change can only be paid with the customer's own dimes.
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
- HTTP tests call every endpoint in-process with PSR-7 requests, covering each status code and the problem
  documents.
- Acceptance tests tell the three scenarios from the task, and longer stories across endpoints, using only the HTTP
  API (even the machine setup goes through SERVICE).
- Architecture tests (Pest `arch()`) enforce the layer rules: the domain and application layers cannot depend
  on HTTP, the database or the framework.

## Known limitations and what I would do next

- **Concurrency.** Two simultaneous requests to the same machine can overwrite each other (last write wins).
  The production fix is a `version` column with a compare-and-swap on save, mapped to a 409 and a retry.
- **Unexpected failures are not logged yet.** A 500 hides its details from the client, so a production deployment needs
  a PSR-3 logger plugged into the error handler.
- **No authentication.** SERVICE is open to anyone who can reach the service. An API key header on that route would be
  the first step.
- **The seed is a start-up script**, a stand-in for a real migration or an admin process that creates machines.
