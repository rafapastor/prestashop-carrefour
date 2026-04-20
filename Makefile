SHELL := /bin/bash

MODULE_NAME    := carrefourmarketplace
MODULE_DIR     := $(MODULE_NAME)
VERSION        := $(shell grep -E "this->version\s*=\s*'" $(MODULE_DIR)/$(MODULE_NAME).php | head -1 | sed -E "s/.*'([^']+)'.*/\1/")
DIST_DIR       := dist
ZIP_NAME       := $(MODULE_NAME)-$(VERSION).zip

.DEFAULT_GOAL := help

.PHONY: help
help: ## Show this help
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# ---------- Local dev (Docker) ----------

.PHONY: dev
dev: ## Start PrestaShop + MySQL via Docker
	docker compose up -d
	@echo ""
	@echo "PrestaShop:   http://localhost:8081"
	@echo "Admin:        http://localhost:8081/admindev"
	@echo "Credentials:  admin@prestashop.com / prestashop_demo"
	@echo ""
	@echo "Module mounted at /var/www/html/modules/$(MODULE_NAME)"
	@echo "Install it from Modules -> Module Manager in the admin."

.PHONY: stop
stop: ## Stop the Docker dev environment
	docker compose down

.PHONY: clean-dev
clean-dev: ## Stop AND remove volumes (fresh PS install next time)
	docker compose down -v

.PHONY: logs
logs: ## Tail PrestaShop container logs
	docker compose logs -f prestashop

.PHONY: shell
shell: ## Open a shell inside the PrestaShop container
	docker compose exec prestashop bash

# ---------- Code quality ----------

.PHONY: format
format: ## Apply PrestaShop coding standards
	@if [ ! -f php-cs-fixer.phar ]; then \
		echo "Downloading php-cs-fixer.phar..."; \
		curl -sSfL https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/releases/latest/download/php-cs-fixer.phar -o php-cs-fixer.phar; \
		chmod +x php-cs-fixer.phar; \
	fi
	php php-cs-fixer.phar fix --config=.php-cs-fixer.php

.PHONY: lint
lint: ## Check coding standards (no changes)
	@if [ ! -f php-cs-fixer.phar ]; then \
		echo "Downloading php-cs-fixer.phar..."; \
		curl -sSfL https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/releases/latest/download/php-cs-fixer.phar -o php-cs-fixer.phar; \
		chmod +x php-cs-fixer.phar; \
	fi
	php php-cs-fixer.phar fix --config=.php-cs-fixer.php --dry-run --diff

.PHONY: test
test: ## Run PHPUnit tests
	@if [ ! -f phpunit.phar ]; then \
		echo "Downloading phpunit.phar..."; \
		curl -sSfL https://phar.phpunit.de/phpunit-9.phar -o phpunit.phar; \
		chmod +x phpunit.phar; \
	fi
	php phpunit.phar --no-coverage --colors=always

.PHONY: test-coverage
test-coverage: ## Run tests with coverage report (requires Xdebug or PCOV)
	@if [ ! -f phpunit.phar ]; then \
		curl -sSfL https://phar.phpunit.de/phpunit-9.phar -o phpunit.phar; \
		chmod +x phpunit.phar; \
	fi
	php phpunit.phar --coverage-text --coverage-html=coverage/

# ---------- Packaging (for GitHub releases, NOT Addons) ----------

.PHONY: package
package: ## Build installable ZIP for GitHub releases
	@mkdir -p $(DIST_DIR)
	@rm -f $(DIST_DIR)/$(ZIP_NAME)
	@echo "Packaging $(MODULE_NAME) v$(VERSION)..."
	@cd . && zip -rq $(DIST_DIR)/$(ZIP_NAME) $(MODULE_DIR) \
		-x "$(MODULE_DIR)/logs/*" \
		-x "$(MODULE_DIR)/**/.DS_Store" \
		-x "$(MODULE_DIR)/**/*.log"
	@echo "Done: $(DIST_DIR)/$(ZIP_NAME)"
	@ls -lh $(DIST_DIR)/$(ZIP_NAME)

# ---------- Housekeeping ----------

.PHONY: clean
clean: ## Remove build artifacts and caches
	rm -rf $(DIST_DIR)
	rm -f .php-cs-fixer.cache
	rm -rf .phpunit.result.cache coverage/

.PHONY: version
version: ## Print the current module version
	@echo $(VERSION)
