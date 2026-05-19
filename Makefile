.PHONY: help reset cli pull build up run assets_serve clean

DOCKER_COMPOSE = EXTERNAL_USER_ID=$(shell id -u) docker compose

help: ## display this help message
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

pull: ## Build the docker images
	@$(DOCKER_COMPOSE) pull --ignore-pull-failures

build: ## Build the docker images
	@$(DOCKER_COMPOSE) build

agent/reset:
	@$(DOCKER_COMPOSE) run --rm agent composer reset

backend/reset: ## Reset (or create) the database
	@$(DOCKER_COMPOSE) exec backend-php composer reset

cli: ## Open a CLI in the PHP container
	@$(DOCKER_COMPOSE) exec backend-php bash

backend/infra/docker/tls/cert.pem:
	mkcert -key-file backend/infra/docker/php/tls/key.pem -cert-file backend/infra/docker/php/tls/cert.pem localhost

agent/vendor: agent/composer.json
	@$(DOCKER_COMPOSE) run --rm agent composer install

backend/vendor: backend/composer.json backend/composer.lock backend/symfony.lock
	@$(DOCKER_COMPOSE) run --rm backend-php composer install

backend/node_modules: backend/package.json backend/yarn.lock
	@$(DOCKER_COMPOSE) run --rm backend-php yarn

up: ## Turn-on the containers
	@mkdir -p agent/var/data
	@$(DOCKER_COMPOSE) up -d --remove-orphans

.configured:
	test -f .configured || make first_run
	touch .configured

run: .configured up ## Run the project. Create the Database  and build the images if needed

first_run: backend/infra/docker/tls/cert.pem pull build agent/vendor backend/vendor backend/node_modules agent/reset up backend/reset backend/public/build

psalm:
	@$(DOCKER_COMPOSE) run --rm backend-php ./vendor/bin/psalm

psalm_vener:
	@$(DOCKER_COMPOSE) run --rm backend-php ./vendor/bin/psalm --show-info=true

test:
	@$(DOCKER_COMPOSE) run --rm backend-php ./bin/phpunit

backend/public/build: backend/assets/** backend/yarn.lock backend/webpack.config.js
	@$(DOCKER_COMPOSE) run --rm backend-php yarn build

assets_serve:
	@$(DOCKER_COMPOSE) exec backend-php yarn dev-server

clean:
	@$(DOCKER_COMPOSE) down -v
	@rm -rf \
		.configured \
		agent/var \
		agent/vendor \
		backend/infra/docker/tls/cert.pem \
		backend/infra/docker/tls/key.pem \
		backend/node_modules \
		backend/public/build\
		backend/public/bundles  \
		backend/var \
		backend/vendor \
