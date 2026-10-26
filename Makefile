SHELL=bash
SOURCE_DIR = $(shell pwd)
BIN_DIR = ${SOURCE_DIR}/vendor/bin
COMPOSER = composer

_CYAN=\033[36m
_GREEN=\033[32m
_END=\033[0m

define printSection
	@printf "${_CYAN}\n══════════════════════════════════════════════════\n${_END}"
	@printf "${_CYAN} $1 ${_END}"
	@printf "${_CYAN}\n══════════════════════════════════════════════════\n${_END}"
endef

.PHONY: all
all: ci ## Run all checks

#  _   _      _
# | | | |    | |
# | |_| | ___| |_ __
# |  _  |/ _ \ | '_ \
# | | | |  __/ | |_) |
# \_| |_/\___|_| .__/
#              | |
#              |_|

.PHONY: help
help: ## Display the list of commands
	$(call printSection,HELP)
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) \
	| sort \
	| awk 'BEGIN {FS = ":.*?## "}; {printf "${_GREEN}%-20s${_END} %s\n", $$1, $$2}' \
	| sed -e 's/##//'

#  _____
# /  __ \
# | /  \/ ___  _ __ ___  _ __   ___  ___  ___ _ __
# | |    / _ \| '_ ` _ \| '_ \ / _ \/ __|/ _ \ '__|
# | \__/\ (_) | | | | | | |_) | (_) \__ \  __/ |
#  \____/\___/|_| |_| |_| .__/ \___/|___/\___|_|
#                       | |
#                       |_|

.PHONY: install
install: clean-vendor install-vendor ## Install the project

.PHONY: clean-vendor
clean-vendor: ## Remove composer dependencies
	$(call printSection,CLEAN VENDOR)
	rm -rf ${SOURCE_DIR}/vendor

.PHONY: install-vendor
install-vendor: ${SOURCE_DIR}/vendor/composer/installed.json ## Install composer dependencies

${SOURCE_DIR}/vendor/composer/installed.json:
	$(call printSection,INSTALL VENDOR)
	$(COMPOSER) --no-interaction install --ansi --no-progress --prefer-dist

#  _____             _ _ _
# |  _  |           | (_) |
# | | | |_   _  __ _| |_| |_ _   _
# | | | | | | |/ _` | | | __| | | |
# \ \/' / |_| | (_| | | | |_| |_| |
#  \_/\_\\__,_|\__,_|_|_|\__|\__, |
#                             __/ |
#                            |___/

.PHONY: fix
fix: ## Apply the code style (php-cs-fixer)
	$(call printSection,PHP-CS-FIXER)
	${BIN_DIR}/php-cs-fixer fix -vvv

.PHONY: rector
rector: ## Check that Rector has nothing to refactor
	$(call printSection,RECTOR)
	${BIN_DIR}/rector process --dry-run

.PHONY: rector-process
rector-process: ## Apply Rector, then the code style it may have broken
	$(call printSection,RECTOR)
	${BIN_DIR}/rector process
	$(MAKE) --no-print-directory fix

.PHONY: phpstan
phpstan: ## Run static analysis (PHPStan)
	$(call printSection,PHPSTAN)
	${BIN_DIR}/phpstan --memory-limit=1G analyse

.PHONY: dependency
dependency: ## Check for unused or missing composer dependencies
	$(call printSection,COMPOSER DEPENDENCY)
	${BIN_DIR}/composer-dependency-analyser

.PHONY: line-length
line-length: ## Fail if a PHP line exceeds 80 characters (except imports)
	$(call printSection,LINE LENGTH)
	@find src tests rector.php .php-cs-fixer.php -name '*.php' -print0 \
	| LC_ALL=C.UTF-8 xargs -0 awk 'length > 80 && !/^use / \
		{ printf "%s:%d: %d characters\n", FILENAME, FNR, length; bad = 1 } \
		END { exit bad }'

.PHONY: psr4
psr4: ## Check that namespaces follow PSR-4
	$(COMPOSER) dump-autoload --optimize --strict-psr --no-scripts

#  _____         _
# |_   _|       | |
#   | | ___  ___| |_
#   | |/ _ \/ __| __|
#   | |  __/\__ \ |_
#   \_/\___||___/\__|

.PHONY: test
test: ## Run the tests [usage: make test args="--filter=TestName --stop-on-failure"]
	$(call printSection,TEST phpunit)
	${BIN_DIR}/phpunit $(args)

.PHONY: coverage
coverage: ## Run tests with coverage summary (pcov)
	$(call printSection,COVERAGE)
	${BIN_DIR}/phpunit --coverage-text --only-summary-for-coverage-text

.PHONY: infection
infection: ## Run mutation testing (Infection, pcov)
	$(call printSection,INFECTION)
	${BIN_DIR}/infection --threads=max --show-mutations

#  _____ _____
# /  __ \_   _|
# | /  \/ | |
# | |     | |
# | \__/\_| |_
#  \____/\___/

.PHONY: ci
ci: ## Run all CI checks
	$(call printSection,CI)
	$(COMPOSER) validate
	${BIN_DIR}/php-cs-fixer fix --dry-run --diff
	$(MAKE) --no-print-directory line-length
	$(MAKE) --no-print-directory rector
	@test ! -f phpstan-baseline.neon || { echo "phpstan-baseline.neon forbidden: fix the errors instead of hiding them" >&2; exit 1; }
	$(MAKE) --no-print-directory psr4
	$(MAKE) --no-print-directory phpstan
	$(MAKE) --no-print-directory dependency
	$(COMPOSER) audit
	$(MAKE) --no-print-directory test
	$(MAKE) --no-print-directory infection
