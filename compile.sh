#!/bin/bash

set -o pipefail  # trace ERR through pipes
set -o errtrace  # trace ERR through 'time command' and other functions
set -o nounset   ## set -u : exit the script if you try to use an uninitialised variable
set -o errexit   ## set -e : exit the script if any statement returns a non-true return value
set -x

SCRIPT_DIR=$(dirname $(readlink -f $0))

## copy configs
cp "$SCRIPT_DIR/Documentation/Examples/clisync.yml"  "$SCRIPT_DIR/src/conf/"

## run composer
cd "$SCRIPT_DIR/src"
composer install --no-dev
composer dump-autoload --optimize --no-dev

## create phar
cd "$SCRIPT_DIR"

BOX_PATH=vendor/box.phar
if [[ ! -f vendor/box.phar ]]; then
    mkdir -p vendor
    wget -O${BOX_PATH} https://github.com/box-project/box/releases/download/4.3.8/box.phar
fi

php -d phar.readonly=0 "$SCRIPT_DIR/$BOX_PATH" compile -c box.json

echo "Build finished, for installation run: make install"
