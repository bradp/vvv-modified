#!/usr/bin/env bash
set -eo pipefail

# @description Adds the homebin folder to PATH
# @noargs
function setup_vvv_env() {
  # fix no tty warnings in provisioner logs
  sed -i '/tty/!s/mesg n/tty -s \\&\\& mesg n/' /root/.profile

  # add homebin to secure_path setting for sudo, clean first and then append at the end
  sed -i -E \
    -e "s|:/srv/config/homebin||" \
    -e "s|/srv/config/homebin:||" \
    -e "s|(.*Defaults.*secure_path.*?\".*?)(\")|\1:/srv/config/homebin\2|" \
    /etc/sudoers

  # add homebin to the default environment, clean first and then append at the end
  sed -i -E \
    -e "s|:/srv/config/homebin||" \
    -e "s|/srv/config/homebin:||" \
    -e "s|(.*PATH.*?\".*?)(\")|\1:/srv/config/homebin\2|" \
    /etc/environment
}

# @description Remove MOTD output from Ubuntu and add our own
# @noargs
function cleanup_terminal_splash() {
    rm -f /etc/update-motd.d/00-header
    rm -f /etc/update-motd.d/10-help-text
    rm -f /etc/update-motd.d/50-motd-news
    rm -f /etc/update-motd.d/51-cloudguest
    rm -f /etc/update-motd.d/50-landscape-sysinfo
    rm -f /etc/update-motd.d/80-livepatch
    rm -f /etc/update-motd.d/90-updates-available
    rm -f /etc/update-motd.d/91-release-upgrade
    rm -f /etc/update-motd.d/95-hwe-eol
    rm -f /etc/update-motd.d/98-cloudguest
    rm -f /etc/update-motd.d/00-vvv-bash-splash
}

# @description Sets up the VVV users bash profile, and configuration files
# @noargs
function profile_setup() {
  vvv_info " * Setting ownership of files in /home/vagrant to vagrant"
  chown -R vagrant:vagrant /home/vagrant/

  # Copy custom dotfiles and bin file for the vagrant user from local
  vvv_info " * Copying /srv/provision/utilities/env/bash_profile to /home/vagrant/.bash_profile"
  rm -f "/home/vagrant/.bash_profile"
  noroot cp -f "/srv/provision/utilities/env/bash_profile" "/home/vagrant/.bash_profile"

  vvv_info " * Copying /srv/provision/utilities/env/bash_aliases to /home/vagrant/.bash_aliases"
  rm -f "/home/vagrant/.bash_aliases"
  noroot cp -f "/srv/provision/utilities/env/bash_aliases" "/home/vagrant/.bash_aliases"

  vvv_info " * Copying /srv/provision/utilities/env/bash_aliases to ${HOME}/.bash_aliases"
  rm -f "${HOME}/.bash_aliases"
  cp -f "/srv/provision/utilities/env/bash_aliases" "${HOME}/.bash_aliases"

  if [ -d "/etc/ssh" ]; then
    vvv_info " * Copying /srv/provision/utilities/env/ssh/ssh_known_hosts to /etc/ssh/ssh_known_hosts"
    cp -f /srv/provision/utilities/env/ssh/ssh_known_hosts /etc/ssh/ssh_known_hosts
    vvv_info " * Copying /srv/provision/utilities/env/ssh/sshd_config to /etc/ssh/sshd_config"
    cp -f /srv/provision/utilities/env/ssh/sshd_config /etc/ssh/sshd_config
    if [ "${VVV_DOCKER}" != 1 ]; then
      vvv_info " * Reloading SSH Daemon"
      systemctl reload ssh 1> /dev/null
    fi
  fi
}

# @description Sets up the main VVV user profile
# @noargs
function vvv_init_profile() {
  # Profile_setup
  vvv_info " * Bash profile setup and directories."
  setup_vvv_env
  cleanup_terminal_splash
  profile_setup
}

vvv_add_hook init vvv_init_profile 0
