# Makefile

COMPOSE_FILE := compose.yaml
SERVICE      := testgator-server

DOCKER_COMPOSE := docker compose

EXEC := $(DOCKER_COMPOSE) exec $(SERVICE)

.PHONY: help build up start stop down restart logs bash sh composer console ps clean test tests

help:
	@echo "Available targets:"
	@echo "  build      Build dev images"
	@echo "  up/start   Start containers"
	@echo "  stop       Stop containers"
	@echo "  down       Stop and remove containers"
	@echo "  restart    Restart containers"
	@echo "  logs       Tail logs"
	@echo "  bash/sh    Open shell in app container"
	@echo "  composer   Run composer in app container (ARGS=...)"
	@echo "  console    Run Symfony console (CMD=...)"
	@echo "  clean      Remove containers, networks, volumes"
	@echo "  db-0      	Reset db and load fixtures"
	@echo "  qa      	Run rector, cs-fixer, phpstan analyse and test"
	@echo "  test/tests	Run phpunit tests"

build:
	$(DOCKER_COMPOSE) build $(SERVICE)

up:
	# Start only the app service — the test DB is started on demand by `make test`.
	$(DOCKER_COMPOSE) up --wait $(SERVICE)

start: up

stop:
	$(DOCKER_COMPOSE) stop

down:
	$(DOCKER_COMPOSE) down

restart:
	make down
	make up-d

db-0:
	$(DOCKER_COMPOSE) exec $(SERVICE) sh -lc '\
	php bin/console doctrine:query:sql "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = current_database() AND pid <> pg_backend_pid()"; \
	php bin/console doctrine:database:drop --force --if-exists; \
	php bin/console doctrine:database:create --if-not-exists; \
	php bin/console doctrine:schema:update --force; \
	php bin/console doctrine:fixture:load --purge-with-truncate --no-interaction \
	'

logs:
	$(DOCKER_COMPOSE) logs -f $(SERVICE)

bash:
	$(DOCKER_COMPOSE) exec $(SERVICE) bash || $(DOCKER_COMPOSE) exec $(SERVICE) sh

sh:
	$(DOCKER_COMPOSE) exec $(SERVICE) sh

composer:
	$(DOCKER_COMPOSE) exec $(SERVICE) composer $(ARGS)

console:
	$(DOCKER_COMPOSE) exec $(SERVICE) php bin/console $(CMD)

clean:
	$(DOCKER_COMPOSE) down -v

qa:
	@set -e; \
	$(EXEC) vendor/bin/rector --dry-run; \
	$(EXEC) vendor/bin/php-cs-fixer fix --dry-run --diff; \
	$(EXEC) vendor/bin/phpstan analyse --memory-limit=1G; \
	$(EXEC) vendor/bin/deptrac analyse \

test:
	@set -e; \
	$(DOCKER_COMPOSE) up -d --wait testgator-db-test; \
	trap '$(DOCKER_COMPOSE) stop testgator-db-test' EXIT; \
	$(EXEC) php bin/console cache:clear --env=test; \
	$(EXEC) php bin/console doctrine:database:create --if-not-exists --env=test; \
	$(EXEC) php bin/console doctrine:schema:update --force --env=test; \
	$(EXEC) vendor/bin/phpunit --display-all-issues

tests: test

