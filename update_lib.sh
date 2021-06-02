#!/bin/bash

CWD=`pwd`
SED_FILE="${CWD}/update_lib.sed"
echo $SED_FILE

function pushd {
    command pushd "$@" > /dev/null
}

function popd {
    command popd "$@" > /dev/null
}

function check_commands {
  for app in "curl" "jq";
  do
    if ! [ -x "$(command -v $app)" ]; then
      echo "Error: $app is not installed." >&2
      exit 1
    fi
  done
}

check_commands
pushd doofinder-for-woocommerce

rm -rf lib
DOWNLOAD_URL=`curl -s https://api.github.com/repos/doofinder/php-doofinder/releases/latest | jq -r ".tarball_url"`
curl -L $DOWNLOAD_URL | tar xzv
mv `find . -type d -name doofinder-php-doofinder-*` lib

pushd lib

composer update --no-dev

rm -rf .gitignore swagger .git src/Search/Test
rm .travis.yml CHANGELOG.md index.php NOTAS phpunit.xml
find . -name "docs" -type d -print0 | xargs -0 rm -rf
find . -name ".github" -type d -print0 | xargs -0 rm -rf
find . -name "*.md" -print0 | xargs -0 rm -rf

pushd vendor

find . -name "*.md" -print0 | xargs -0 rm
find . -name "Dockerfile" -print0 | xargs -0 rm
find . -name "*.json" -print0 | xargs -0 rm
find . -name "*.pubkey*" -print0 | xargs -0 rm
find . -name "*.php_cs*" -print0 | xargs -0 rm
find . -name "phpstan*" -print0 | xargs -0 rm
find . -name "Makefile" -print0 | xargs -0 rm
find . -name "*.xml" -print0 | xargs -0 rm

popd # vendor

# prefix dependencies namespaces with Doofinder\
find . -type f -name "*.php" -exec sed -i .bak -f $SED_FILE {} +
find . -name "*.bak" -exec rm {} +

popd  # lib
popd  # doofinder-for-woocommerce
