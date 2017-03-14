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
  wp core install --url=localhost:8000 --title=WooCommerce --admin_user=admin --admin_password=admin123 --admin_email=foo@bar.com --skip-email

  wp plugin install wordpress-importer --activate
  wp plugin install woocommerce --activate
  wp import wp-content/plugins/woocommerce/dummy-data/dummy-data.xml --authors=create

  chown -R www-data:www-data /var/www/html

  ln -s /usr/src/doofinder-for-woocommerce /var/www/html/wp-content/plugins
  wp plugin activate doofinder-for-woocommerce
fi

# Pass through arguments to exec
if [ $# -ge 1 ]; then
  exec "$@"
fi
