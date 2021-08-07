# bash_profile
#
# Symlinked to the vagrant user's home directory. This loads
# the default .bashrc provided by the virtual machine, which in
# turn loads the .bash_aliases file that we provide. Use this
# bash_profile to set environment variables and such.

export PATH="$PATH:/srv/www/phpcs/vendor/bin"

# set variable identifying the chroot you work in (used in the prompt below)
if [ -z "${debian_chroot:-}" ] && [ -r /etc/debian_chroot ]; then
    debian_chroot=$(cat /etc/debian_chroot)
fi
red="\[\033[38;5;9m\]"
green="\[\033[01;32m\]"
blue="\[\033[38;5;4m\]"
yellow="\[\033[38;5;3m\]"
white="\[\033[00m\]"
PS1="${debian_chroot:+($debian_chroot)}${red}\u${green}@${blue}\h${white}:${yellow}\w$ \[\033[0m\]"

cd "/srv/www"
