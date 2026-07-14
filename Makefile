.PHONY: help build up down restart logs shell install fresh migrate seed admin test pint lint typecheck format ci locks clean

# Default target
help:
	@echo "Marketplace Platform — Make targets"
	@echo ""
	@echo "  make build       — Build Docker images"
	@echo "  make up          — Start all containers"
	@echo "  make down        — Stop all containers"
	@echo "  make restart     — Restart all containers"
	@echo "  make logs        — Tail logs (Ctrl+C to exit)"
	@echo "  make shell       — Bash into the app container"
	@echo ""
	@echo "  make install     — First-time setup: generates composer.lock + package-lock.json,"
	@echo "                     installs deps, migrates, seeds. Commit the locks afterward."
	@echo "  make locks       — Only generate lock files (without starting the full stack)"
	@echo "  make fresh       — migrate:fresh --seed  (DROPS ALL DATA)"
	@echo "  make migrate     — Run pending migrations"
	@echo "  make seed        — Run database seeders"
	@echo "  make admin       — Create a Filament admin user interactively"
	@echo ""
	@echo "  make test        — Run the test suite (Pest)"
	@echo "  make pint        — Format PHP with Laravel Pint"
	@echo "  make lint        — ESLint on the frontend"
	@echo "  make typecheck   — tsc --noEmit on the frontend"
	@echo "  make format      — Prettier on the frontend"
	@echo "  make ci          — Run the full CI sequence locally"
	@echo ""
	@echo "  make clean       — Remove containers + volumes (DROPS ALL DATA)"

# ─────────────────────────── Docker lifecycle ──────────────────────────
build:
	docker compose build

up:
	docker compose up -d
	@echo ""
	@echo "✓ Stack starting. Once healthy:"
	@echo "    Storefront →  http://localhost:8000"
	@echo "    Admin      →  http://localhost:8000/admin"
	@echo "    Mailpit    →  http://localhost:8025"
	@echo "    Meilisearch→  http://localhost:7700"
	@echo "    MinIO UI   →  http://localhost:9001  (minioadmin / minioadmin)"
	@echo "    Vite HMR   →  http://localhost:5173"

down:
	docker compose down

restart:
	docker compose restart

logs:
	docker compose logs -f --tail=100

shell:
	docker compose exec app bash

# ─────────────────── First-time install (generates lock files) ─────────
install:
	@test -f .env || cp .env.example .env
	docker compose up -d
	@echo "▶ Waiting up to 30s for services to become healthy…"
	@for i in $$(seq 1 30); do \
	    if docker compose ps --format json 2>/dev/null | grep -q '"Health":"healthy"'; then break; fi; \
	    sleep 1; \
	done
	@echo "▶ Installing composer dependencies (generates composer.lock)…"
	docker compose exec -T app composer install --no-interaction --prefer-dist
	@echo "▶ Generating application key…"
	docker compose exec -T app php artisan key:generate --force
	docker compose exec -T app php artisan storage:link
	@echo "▶ Running migrations + seeders…"
	docker compose exec -T app php artisan migrate --force
	docker compose exec -T app php artisan db:seed --force
	@echo "▶ Installing npm dependencies (generates package-lock.json)…"
	docker compose exec -T vite npm install
	@echo ""
	@echo "✓ Install complete."
	@echo ""
	@echo "  Storefront → http://localhost:8000"
	@echo "  Admin login → admin@marketplace.test / password"
	@echo ""
	@echo "▶ NEXT STEP: commit the generated lock files for reproducible builds:"
	@echo "    git add composer.lock package-lock.json"
	@echo "    git commit -m \"chore: lock dependency versions\""

# Generate just the lock files (faster than full install, useful for CI bootstrap)
locks:
	@test -f .env || cp .env.example .env
	@echo "▶ Generating composer.lock via one-shot container…"
	docker run --rm -v "$$(pwd)":/app -w /app composer:2 \
	    composer update --lock --no-scripts --no-interaction
	@echo "▶ Generating package-lock.json via one-shot container…"
	docker run --rm -v "$$(pwd)":/app -w /app node:20-alpine \
	    npm install --package-lock-only --no-audit --no-fund
	@echo "✓ Lock files generated. Commit them now."

# ─────────────────────────── Database ──────────────────────────────────
fresh:
	docker compose exec app php artisan migrate:fresh --seed --force

migrate:
	docker compose exec app php artisan migrate --force

seed:
	docker compose exec app php artisan db:seed --force

admin:
	docker compose exec app php artisan make:filament-user

# ──────────────────────────── Quality ─────────────────────────────────
test:
	docker compose exec app php artisan test

pint:
	docker compose exec app ./vendor/bin/pint

lint:
	docker compose exec vite npm run lint

typecheck:
	docker compose exec vite npm run typecheck

format:
	docker compose exec vite npm run format

ci: pint test lint typecheck
	@echo "✓ Local CI passed"

# ──────────────────────────── Cleanup ─────────────────────────────────
clean:
	docker compose down -v
	@echo "✓ Containers and volumes removed."
