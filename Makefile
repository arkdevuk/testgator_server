# Makefile

COMPOSE_FILE := compose.yml
SERVICE      := testgator-server

DOCKER_COMPOSE := docker compose -f $(COMPOSE_FILE)

EXEC := $(DOCKER_COMPOSE) exec $(SERVICE)

# compose.yml's env_file loads .env.local into the container's REAL process
# environment at container-start time (Caddy needs some of those as real env
# vars). Symfony's Dotenv (see Dotenv::populate()) will never override a var
# that's already really set — `-e APP_ENV=test` only fixes APP_ENV itself;
# every other key your .env.local also defines (DATABASE_URL, AWS_*, ...)
# keeps winning over .env.test regardless. `--env=test` on bin/console does
# nothing either — Symfony dropped that console shortcut years ago.
# The only reliable fix: source .env.test's values as real shell env vars
# *inside* the exec'd process itself, which overwrites whatever the
# container inherited, no Dotenv precedence rules involved. `docker compose
# exec` has no --env-file flag, so this goes through `sh -c`.
LOAD_TEST_ENV := set -a; . /app/.env.test; set +a;

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

# JWTService (src/Services/Authentification/JWTService.php) reads an RS256
# keypair from data/JWT.test/ at runtime. Like data/JWT.prod, it's not
# committed (see .gitignore's /data/JWT.*) — but unlike prod's key, the
# test one protects nothing real (it only ever signs tokens inside an
# ephemeral phpunit run), so there's no reason to make every contributor or
# CI run generate it by hand. This rule only fires when the file doesn't
# already exist, so it's a no-op on repeat runs.
data/JWT.test/testgator.key:
	mkdir -p data/JWT.test
	openssl genrsa -out data/JWT.test/testgator.key 4096
	openssl rsa -in data/JWT.test/testgator.key -pubout -out data/JWT.test/testgator.pub

test: data/JWT.test/testgator.key
	@set -e; \
	$(DOCKER_COMPOSE) up -d --wait testgator-db-test testgator-s3-test; \
	trap '$(DOCKER_COMPOSE) stop testgator-db-test testgator-s3-test' EXIT; \
	$(DOCKER_COMPOSE) exec testgator-s3-test sh -c '\
		printf "s3.bucket.create -name testgator-test-private\ns3.bucket.create -name testgator-test-public\n" | weed shell \
	' || true; \
	$(EXEC) sh -c '$(LOAD_TEST_ENV) php bin/console cache:clear'; \
	$(EXEC) sh -c '$(LOAD_TEST_ENV) php bin/console doctrine:database:create --if-not-exists'; \
	$(EXEC) sh -c '$(LOAD_TEST_ENV) php bin/console doctrine:schema:update --force'; \
	$(EXEC) sh -c '$(LOAD_TEST_ENV) vendor/bin/phpunit --display-all-issues'

tests: test

