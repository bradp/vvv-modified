#!/usr/bin/env bash
# @description PHP Codesniffer
set -eo pipefail

function php_codesniff_setup() {
  export COMPOSER_ALLOW_SUPERUSER=1
  export COMPOSER_NO_INTERACTION=1

  vvv_info " * Provisioning PHP_CodeSniffer"

  noroot mkdir -p /srv/provision/phpcs
  noroot cp -f "/srv/provision/utilities/phpcs/composer.json" "/srv/provision/phpcs/composer.json" 1> /dev/null
  cd /srv/provision/phpcs
  COMPOSER_BIN_DIR="bin" noroot composer update --no-ansi --no-progress -q 1> /dev/null

  vvv_info " * Setting Parbs as the default PHPCodesniffer standard"
  noroot /srv/provision/phpcs/bin/phpcs --config-set default_standard Parbs 1> /dev/null

  vvv_success " * PHPCS provisioning has ended"
}
export -f php_codesniff_setup

vvv_add_hook after_composer php_codesniff_setup
