# Vending Machine

A vending machine modelled as a small HTTP service in PHP 8.5.
Work in progress: this README grows with the code.

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
```

## Status

Docker and tooling are in place (nginx, php-fpm, Pest, PHPStan, PHP-CS-Fixer).
The domain model and API are next.