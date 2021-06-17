#!/bin/bash

# Apply patches if WordPress linter doesn't accept code.
# It's recommended to not execute this after each deps update so we can
# see if they updated their linter and stop patching code that became
# valid

BASEDIR=`pwd`
PATCHESDIR="${BASEDIR}/patches"

pushd doofinder-for-woocommerce/lib/vendor

# https://wordpress.org/support/topic/errors-parsing-standard-input-code-bitwise-or/
find . -type f -name bootstrap80.php -exec sed -i .bak -f "${PATCHESDIR}/bootstrap80.php.sed" {} +
find . -name "*.bak" -exec rm {} +

popd
