.PHONY: help test lint check install

PHP ?= php

help:
	@echo "Available targets:"
	@echo "  test     - Run the test suite"
	@echo "  lint     - Check the syntax of every PHP file"
	@echo "  check    - lint, then test"
	@echo "  install  - Generate the autoloader (no dependency to download)"

test: vendor/autoload.php
	@$(PHP) tests/run.php

lint:
	@find bin src tests -type f ! -name '*.md' -print0 \
		| xargs -0 -n1 $(PHP) -l > /dev/null
	@echo "Syntax OK."

check: lint test

install: vendor/autoload.php

vendor/autoload.php: composer.json
	composer install --no-interaction
