.PHONY: setup preview up down build fresh test seed demo logs sh web api-sh queue-restart

# Where the preview answers. In GitHub Codespaces, port 8080 is forwarded to
# a private https address named after the codespace; anywhere else, localhost.
PREVIEW_URL ?= $(if $(CODESPACE_NAME),https://$(CODESPACE_NAME)-8080.$(GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN),http://localhost:8080)
PREVIEW_HOST = $(patsubst https://%,%,$(patsubst http://%,%,$(PREVIEW_URL)))

setup: ## First run from a fresh clone: settings, containers, dependencies, demo database
	test -f api/.env || cp api/.env.example api/.env
	test -f .env || printf 'UID=%s\nGID=%s\n' "$$(id -u)" "$$(id -g)" > .env
	docker compose up -d --build
	docker compose exec php composer install
	grep -q '^APP_KEY=base64:' api/.env || docker compose exec php php artisan key:generate
	docker compose exec php php artisan migrate --seed
	docker compose exec php php artisan db:seed --class=DemoShowcaseSeeder
	npm --prefix web install

preview: ## The whole site on one address (port 8080), as visitors get it: what Codespaces opens
	test -f api/vendor/autoload.php || $(MAKE) setup
	test -d web/node_modules || npm --prefix web install
	sed -e 's|^APP_URL=.*|APP_URL=$(PREVIEW_URL)|' \
	    -e 's|^FRONTEND_URL=.*|FRONTEND_URL=$(PREVIEW_URL)|' \
	    -e 's|^SESSION_DOMAIN=.*|SESSION_DOMAIN=null|' \
	    -e 's|^SANCTUM_STATEFUL_DOMAINS=.*|SANCTUM_STATEFUL_DOMAINS=localhost:5174,localhost:8000,$(PREVIEW_HOST)|' \
	    api/.env > api/.env.preview && mv api/.env.preview api/.env
	VITE_API_URL= VITE_SITE_URL=$(PREVIEW_URL) npm --prefix web run build
	docker compose --profile preview up -d
	docker compose restart queue
	@echo "Construye Academy: $(PREVIEW_URL)"

up: ## Start all containers
	docker compose up -d

down: ## Stop all containers
	docker compose down

build: ## Rebuild php image
	docker compose build

fresh: ## Drop + migrate + seed the database
	docker compose exec php php artisan migrate:fresh --seed

test: ## Run the Pest test suite (against mysql-test)
	docker compose exec php php artisan test

seed: ## Re-run seeders
	docker compose exec php php artisan db:seed

demo: ## Seed local demo data (partners, promo, reviews) — never for production
	docker compose exec php php artisan db:seed --class=DemoShowcaseSeeder

review: ## Regenerate api/content-review.md (passages a validar)
	docker compose exec php php artisan content:review

logs: ## Tail container logs
	docker compose logs -f --tail=100

sh: ## Shell into the php container
	docker compose exec php bash

web: ## Start the Vite dev server (host)
	cd web && npm run dev

queue-restart: ## Restart the queue worker
	docker compose restart queue
