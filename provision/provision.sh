#!/usr/bin/env bash

. "/srv/provision/utilities/env/bash_aliases"


if [ -x "$(command -v ntpdate)" ]; then
	echo " * Syncing clocks"
	sudo ntpdate -u ntp.ubuntu.com 1> /dev/null
fi

rm -rf /vagrant/failed_provisioners
rm -f /vagrant/version
rm -f /vagrant/config.yml

mkdir -p /vagrant
mkdir -p /vagrant/failed_provisioners

cp -f /home/vagrant/version /vagrant/version
chmod 0644 /vagrant/version
rm -f /home/vagrant/version

cp -f /srv/config/config.yml /vagrant/config.yml && chmod 0644 /vagrant/config.yml
chown -R vagrant:vagrant /vagrant

export VVV_CONFIG=/vagrant/config.yml

export APT_KEY_DONT_WARN_ON_DANGEROUS_USAGE=1
export VVV_PACKAGE_LIST=()
export VVV_PACKAGE_REMOVAL_LIST=()

###############################
####### source files ##########
###############################
. "/srv/provision/provisioners.sh"

. "/srv/provision/utilities/env/provision.sh"
. "/srv/provision/utilities/vvv/provision.sh"
. "/srv/provision/utilities/git/provision.sh"
. "/srv/provision/utilities/mariadb/provision.sh"
. "/srv/provision/utilities/postfix/provision.sh"
. "/srv/provision/utilities/nginx/provision.sh"
. "/srv/provision/utilities/memcached/provision.sh"
. "/srv/provision/utilities/php/provision.sh"
. "/srv/provision/utilities/composer/provision.sh"
. "/srv/provision/utilities/mailhog/provision.sh"
. "/srv/provision/utilities/phpcs/provision.sh"

###############################
####### init ##################
###############################
vvv_hook init

if ! network_check; then
  exit 1
fi

###############################
####### before packages #######
###############################
vvv_info " * Packages check and install."
vvv_hook before_packages

vvv_info " * Checking for apt packages to remove."
if ! vvv_apt_package_remove ${VVV_PACKAGE_REMOVAL_LIST[@]}; then
  vvv_error " * Main packages removal failed, halting provision"
  exit 1
fi

vvv_info " * Upgrading apt packages."
vvv_apt_packages_upgrade

vvv_info " * Checking for apt packages to install."
if ! vvv_package_install ${VVV_PACKAGE_LIST[@]}; then
  vvv_error " * Main packages check and install failed, halting provision"
  exit 1
fi

###############################
####### after packages ########
###############################
vvv_info " * Running tools_install"
vvv_hook after_packages

###############################
####### finalize ##############
###############################
vvv_info " * Finalizing"
vvv_hook finalize

provisioner_success
