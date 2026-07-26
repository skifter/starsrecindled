PHP ?= php
COMPOSER ?= composer

.PHONY: validate test frontend-check

validate:
	find src config migrations tests -type f -name '*.php' -print0 | xargs -0 -n1 $(PHP) -l
	$(COMPOSER) validate --strict

test:
	$(PHP) vendor/bin/phpunit

frontend-check:
	cd frontend && npm run check
