.PHONY: all init init-with-data start stop clean dev-console logs cache-flush db-backup db-restore doofinder-configure doofinder-install doofinder-uninstall doofinder-reinstall consistency lint-php-versions

# Include environment variables from .env file
ifeq ("$(wildcard .env)","")
	$(error Please ensure the `.env` file is present in the root directory.)
endif

include .env
export

docker_compose ?= docker compose
ifneq ("$(wildcard .env.local)","")
	include .env.local
	export
	docker_compose = docker compose --env-file .env --env-file .env.local
endif

docker_exec_web = $(docker_compose) exec wordpress
wp = $(docker_exec_web) wpcli --path=/var/www/html --allow-root

envsubst_vars = $$PLUGIN_VERSION,$$DOOFINDER_PLUGINS_URL_FORMAT,$$DOOFINDER_API_URL_FORMAT,$$DOOFINDER_LAYER_SCRIPT_URL

# Default target: list available tasks
all:
	@echo "Before \`make init\` be sure to set up your environment with a proper \`.env\` file."
	@echo "Select a task defined in the Makefile:"
	@echo "  all, init, init-with-data, start, stop, clean, dev-console, logs, cache-flush, doofinder-configure,"
	@echo "  db-backup, db-restore,"
	@echo "  doofinder-install, doofinder-uninstall, doofinder-reinstall,"
	@echo "  consistency, lint-php-versions"

# Regenerate templated plugin source files from `templates/` using values from .env.
doofinder-configure:
	@envsubst '$(envsubst_vars)' < templates/doofinder-for-woocommerce/doofinder-for-woocommerce.php > doofinder-for-woocommerce/doofinder-for-woocommerce.php
	@envsubst '$(envsubst_vars)' < templates/doofinder-for-woocommerce/readme.txt > doofinder-for-woocommerce/readme.txt
	envsubst '$(envsubst_vars)' < templates/doofinder-for-woocommerce/includes/class-doofinder-constants.php > doofinder-for-woocommerce/includes/class-doofinder-constants.php

# Build images, install WordPress, and start containers
init: doofinder-configure
	$(docker_compose) pull --ignore-buildable
	$(docker_compose) build
	$(docker_compose) up -d
	@echo "Waiting for WordPress install to finish..."
	@until $(docker_compose) exec -T wordpress wpcli --path=/var/www/html --allow-root core is-installed >/dev/null 2>&1; do \
		sleep 2; \
	done
	@echo "Storefront: $(BASE_URL)"
	@echo "Back office: $(BASE_URL)/wp-admin (user: $(ADMIN_USER) / pass: $(ADMIN_PASSWORD))"

# Same as `make init`, but also imports WooCommerce's bundled sample products
# (only honored on a fresh install, before WordPress is set up).
init-with-data:
	@IMPORT_SAMPLE_DATA=true $(MAKE) init

# Start the WordPress Docker containers
start:
	@echo "(WordPress) Starting"
	@$(docker_compose) up -d
	@echo "(WordPress) Started"

# Stop the WordPress Docker containers
stop:
	@echo "(WordPress) Stopping"
	@$(docker_compose) down
	@echo "(WordPress) Stopped"

clean:
	@echo "\033[33m⚠️ WARNING ⚠️\033[0m"
	@echo "This will permanently delete"
	@echo "  - All Docker volumes for this project (the MySQL database)"
	@echo "  - The entire ./html directory, including all WordPress core files"
	@echo -n "Type 'DELETE' to confirm removing all volumes and ./html directory: " && read ans && [ "$${ans}" = "DELETE" ]
	$(docker_compose) down -v
	sudo rm -rf ./html

# Open an interactive shell in the wordpress container
dev-console:
	$(docker_exec_web) bash

# Tail logs from the wordpress container
logs:
	@$(docker_compose) logs -f wordpress

# Flush WordPress object cache and transients
cache-flush:
	$(wp) cache flush
	$(wp) transient delete --all

# Backup the MySQL database from the 'db' container and compress the output
db-backup:
	$(docker_compose) exec -T db /usr/bin/mysqldump --no-tablespaces -u root -p$(MYSQL_ROOT_PASSWORD) $(MYSQL_DATABASE) | gzip > backup_$(shell date +%Y%m%d%H%M%S)$(prefix).sql.gz

# Restore the MySQL database using a provided backup file (pass file=<backupfile> as argument)
db-restore:
	@[ -e "$(file)" ] || (echo "Error: 'file' variable not provided. Use file=<backupfile>" && exit 1)
	gunzip < $(file) | $(docker_compose) exec -T db /usr/bin/mysql -u root -p$(MYSQL_ROOT_PASSWORD) $(MYSQL_DATABASE)

# Activate the Doofinder plugin in WordPress
doofinder-install:
	$(wp) plugin activate doofinder-for-woocommerce

# Uninstall the Doofinder plugin: deactivates it and runs register_uninstall_hook
# (drops plugin tables/options) but preserves the source files (the plugin dir is
# bind-mounted from the host, and `wp plugin uninstall` would otherwise delete it).
doofinder-uninstall:
	$(wp) plugin uninstall --deactivate --skip-delete doofinder-for-woocommerce

# Uninstall and re-activate the Doofinder plugin
doofinder-reinstall: doofinder-uninstall doofinder-install

# Check code consistency using PHP Code Sniffer in a one-shot composer container
consistency:
	docker run --rm \
		-v $(shell pwd):/app -v /app/html -v /app/vendor \
		-w /app composer:lts sh -c \
		"composer install && ./vendor/bin/phpcs"

# Run the PHP 7.0–8.4 compatibility lint
lint-php-versions:
	./phplint.sh
