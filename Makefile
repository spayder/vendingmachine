DEV := docker compose run --rm dev

.DEFAULT_GOAL := help

.PHONY: help up down build test coverage shell

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "} {printf "  %-12s %s\n", $$1, $$2}'

up: ## Start the app on http://localhost:8080
	docker compose up --build -d

down: ## Stop and remove containers
	docker compose down

test: ## Run the test suite
	$(DEV) vendor/bin/pest

coverage: ## Run tests with coverage
	$(DEV) vendor/bin/pest --coverage

shell: ## Open a shell in the dev container
	docker compose run --rm dev sh