# VVV

## YOU PROBABLY DONT WANT TO USE THIS, GO USE [VVV](https://github.com/Varying-Vagrant-Vagrants/VVV).

This is a super heavily modified version of [VVV](https://github.com/Varying-Vagrant-Vagrants/VVV) that I'm using while I submit patches upstream.



```bash
git clone git@github.com:bradp/vvv-modified.git vvv
cd vvv
rm LICENSE
rm .gitignore
rm README.md
rm -rf .git
vagrant up --provision
vagrant ssh
```
