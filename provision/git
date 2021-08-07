#!/usr/bin/env bash
# @description apt-get does not have latest version of git, so let's the use ppa repository instead.
set -eo pipefail

# @noargs
function git_register_packages() {
  if ! vvv_src_list_has "git-core/ppa"; then
    # Add ppa repo.
    vvv_info " * Adding ppa:git-core/ppa repository"
    add-apt-repository -y ppa:git-core/ppa 1> /dev/null
    vvv_success " * git-core/ppa added"
  else
    vvv_info " * git-core/ppa already present, skipping"
  fi

  VVV_PACKAGE_LIST+=(
    git
    git-svn
  )
}
vvv_add_hook before_packages git_register_packages
