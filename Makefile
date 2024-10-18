.PHONY: cs-fix cs-check
cs-fix:
	./vendor/bin/phpcbf
cs-check:
	./vendor/bin/phpcs
