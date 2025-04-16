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
  if [ -n "${WORDPRESS_VERSION-}" ]; then
    echo "Current version:"
    wp core version
    echo "Forced WP version:"
    echo "${WORDPRESS_VERSION}"
    wp core download --version=${WORDPRESS_VERSION} --force
  fi
  
  echo "Installing WordPress at ${LOCAL_DOMAIN}"
  wp core install --url=${LOCAL_DOMAIN} --title=WooCommerce --admin_user=${ADMIN_USER} --admin_password=${ADMIN_PASSWORD} --admin_email=${ADMIN_EMAIL} --skip-email

  wp plugin install wordpress-importer --activate
  wp plugin install woocommerce --activate

  if [ -f wp-content/plugins/woocommerce/dummy-data/dummy-data.xml ]; then
    wp import wp-content/plugins/woocommerce/dummy-data/dummy-data.xml --authors=create
  else
    echo "Dummy data file not found. Skipping import."
  fi

  wp plugin activate doofinder-for-woocommerce
fi

# Pass through arguments to exec
if [ $# -ge 1 ]; then
  exec "$@"
fi
