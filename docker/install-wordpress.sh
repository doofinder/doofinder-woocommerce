#!/bin/bash

set -e   # (errexit) Exit if any subcommand or pipeline returns a non-zero status
set -u   # (nounset) Exit on any attempt to use an uninitialised variable

# Alias for WP-cli to include arguments that we want to use everywhere
shopt -s expand_aliases
alias wp="wpcli --path=/var/www/html --allow-root"

# Install Wordpress with wordpress:latest's script
# docker-entrypoint.sh apache2-hello.sh

cd /var/www/html

if ! $(wp core is-installed); then
  echo "Installing WordPress at localhost:${DOCKER_SERVICE_PORT}"
  wp core install --url=localhost:${DOCKER_SERVICE_PORT} --title=WooCommerce --admin_user=admin --admin_password=admin123 --admin_email=foo@bar.com --skip-email

  wp plugin install wordpress-importer --activate
  wp plugin install polylang --activate

  chown -R www-data:www-data /var/www/html

  ln -s /usr/src/doofinder /var/www/html/wp-content/plugins
fi

# Pass through arguments to exec
if [ $# -ge 1 ]; then
  exec "$@"
fi
