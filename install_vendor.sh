#!/bin/bash

pushd doofinder-for-woocommerce/lib
composer install

pushd vendor
find . -name "*.md" -print0 | xargs -0 rm
find . -name "Dockerfile" -print0 | xargs -0 rm
find . -name "*.json" -print0 | xargs -0 rm
find . -name "*.pubkey*" -print0 | xargs -0 rm
find . -name "*.php_cs*" -print0 | xargs -0 rm
find . -name "phpstan*" -print0 | xargs -0 rm
find . -name "Makefile" -print0 | xargs -0 rm
find . -name "*.xml" -print0 | xargs -0 rm
popd

popd
