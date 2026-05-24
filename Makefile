.PHONY: dev test lint analyse setup fresh quality help

help:
	@echo "Laravel Starter Developer Shortcuts"
	@echo "-----------------------------------"
	@echo "make dev       - Run development servers (serve, queue, vite, pail)"
	@echo "make test      - Run all PHPUnit tests with safe memory limit"
	@echo "make lint      - Run Laravel Pint styling fixer"
	@echo "make analyse   - Run PHPStan static analysis"
	@echo "make setup     - Install Composer, npm, run migrations & seeding"
	@echo "make fresh     - Refresh migrations, seed database"
	@echo "make quality   - Execute full quality gate (lint, analyse, test)"

dev:
	composer dev

test:
	php -d memory_limit=512M artisan test

lint:
	vendor/bin/pint

analyse:
	vendor/bin/phpstan analyse --memory-limit=2G

setup:
	composer setup

fresh:
	php artisan migrate:fresh --seed
	php artisan passport:keys --force

quality: lint analyse test
