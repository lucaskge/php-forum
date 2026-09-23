# ---------------------------------------------------------------------------
# Shortcuts for the two servers and the commands you run most often.
#
#   make            the list of targets
#   make up         board on http://127.0.0.1:8080
#   make docs       documentation on http://127.0.0.1:8100
#   make stop       stop both, keep them ready to start again
#
# Everything here is a thin wrapper over docker compose — the equivalent long
# command is in the comment beside each target, so nothing is hidden. With PHP
# installed on the host, `php bin/console serve` and `mkdocs serve` do the same
# two jobs without Docker.
# ---------------------------------------------------------------------------

# Published ports. Set them for good in .env (APP_PORT / DOCS_PORT), or move one
# for a single command with PORT= — which applies only to the target you ran, so
# `make up PORT=8081` never drags the documentation along with it.
APP_PORT     ?= 8080
DOCS_PORT    ?= 8100
export APP_PORT
export DOCS_PORT

up serve-start restart: APP_PORT := $(or $(PORT),$(APP_PORT))
docs: DOCS_PORT := $(or $(PORT),$(DOCS_PORT))

COMPOSE      := docker compose
COMPOSE_ALL  := docker compose --profile docs
CONSOLE      := $(COMPOSE) run --rm app php bin/console

.DEFAULT_GOAL := help
.PHONY: help up down stop start restart status logs docs docs-stop shell console migrate reference test

help: ## Show this list
	@echo 'Coldwire — make <target>'
	@echo
	@grep -hE '^[a-z-]+:.*?## ' $(MAKEFILE_LIST) | awk -F ':.*?## ' '{printf "  %-12s %s\n", $$1, $$2}'
	@echo
	@echo '  board  http://127.0.0.1:$(APP_PORT)        docs  http://127.0.0.1:$(DOCS_PORT)'
	@echo '  move either one with  PORT=  e.g.  make up PORT=8081'

# --- the two servers -------------------------------------------------------

up: ## Start the board — make up PORT=8081 to publish it elsewhere
	@$(COMPOSE) up -d app
	@echo 'Board on http://127.0.0.1:$(APP_PORT)'

docs: ## Start the documentation — make docs PORT=8200 to publish it elsewhere
	@$(COMPOSE_ALL) up -d docs
	@echo 'Documentation on http://127.0.0.1:$(DOCS_PORT) — it rebuilds as you edit /docs'

docs-stop: ## Stop the documentation, leave the board running
	@$(COMPOSE) stop docs

stop: ## Stop both, keep the containers so `make start` is instant
	@$(COMPOSE_ALL) stop

start: ## Start both again after `make stop`
	@$(COMPOSE_ALL) start
	@echo 'Board http://127.0.0.1:8080 · Documentation http://127.0.0.1:8100'

restart: ## Restart both — the quickest way to bounce the server itself
	@$(COMPOSE_ALL) restart

# The container's only process IS the PHP server, so there is no way to stop one
# and keep the other. `restart` bounces it in about a second, which is what
# "restart the server" means here.
serve-stop: ## Stop the board's server, keep the container (see `make status`)
	@$(COMPOSE) stop app

serve-start: ## Start it again — make serve-start PORT=8081 to move it
	@$(COMPOSE) up -d app
	@echo 'Board on http://127.0.0.1:$(APP_PORT)'

# `docker compose down` on its own leaves the docs container behind and then
# fails to remove the network; the profile has to be named for a clean sweep.
down: ## Stop and remove both containers and the network
	@$(COMPOSE_ALL) down

status: ## What is running, and on which ports
	@$(COMPOSE) ps

logs: ## Follow both logs (Ctrl+C to stop watching — the servers keep running)
	@$(COMPOSE_ALL) logs -f

# --- the commands you run most ---------------------------------------------

console: ## Run a console command: make console CMD="routes"
	@$(CONSOLE) $(CMD)

migrate: ## Apply pending migrations
	@$(CONSOLE) migrate

reference: ## Regenerate docs/reference/ from the live code
	@$(CONSOLE) docs:reference

test: ## Run the test suite against the scratch database
	@$(COMPOSE) run --rm -e DB_DATABASE=coldwire app php tests/run.php

shell: ## Open a shell inside the app container
	@$(COMPOSE) run --rm app sh
