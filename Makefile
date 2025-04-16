.PHONY: cs-fix cs-check dump

# Load .env vars
ifneq ("$(wildcard .env)","")
	include .env
endif

TIMESTAMP=$(shell date +"%Y-%m-%d-%H-%M")
DUMP_FILE=wordpress-dump-$(TIMESTAMP).sql
DB_CONTAINER=doofinder-woocommerce-db-1
MYSQL_DATABASE=wordpress
MYSQL_USER=wordpress
MYSQL_PASSWORD=wordpress

cs-fix:
	./vendor/bin/phpcbf
cs-check:
	./vendor/bin/phpcs

dump:
	@echo "Making database dump..."
	@docker exec -i $(DB_CONTAINER) mysqldump -u $(MYSQL_USER) -p$(MYSQL_PASSWORD) --no-tablespaces $(MYSQL_DATABASE) > /tmp/$(DUMP_FILE)
	@cp /tmp/$(DUMP_FILE) ./$(DUMP_FILE)
	@echo "Dump saved as $(DUMP_FILE)"
