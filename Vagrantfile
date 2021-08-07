# frozen_string_literal: true

# -*- mode: ruby -*-
# vi: set ft=ruby ts=2 sw=2 et:
Vagrant.require_version '>= 2.2.4'
require 'yaml'
require 'fileutils'

vagrant_dir = __dir__
branch_c = "\033[38;5;6m" # 111m"
red = "\033[38;5;9m" # 124m"
green = "\033[1;38;5;2m" # 22m"
blue = "\033[38;5;4m" # 33m"
purple = "\033[38;5;5m" # 129m"
docs = "\033[0m"
yellow = "\033[38;5;3m" # 136m"
yellow_underlined = "\033[4;38;5;3m" # 136m"
url = yellow_underlined
creset = "\033[0m"

version = '?'
File.open("#{vagrant_dir}/config/.version", 'r') do |f|
  version = f.read
  version = version.gsub("\n", '')
end

vvv_config_file = File.join(vagrant_dir, 'config/config.yml')

begin
  vvv_config = YAML.load_file(vvv_config_file)
  unless vvv_config['sites'].is_a? Hash
    vvv_config['sites'] = {}

    puts "#{red}config/config.yml is missing a sites section.#{creset}\n\n"
  end
rescue StandardError => e
  puts "#{red}config/config.yml isn't a valid YAML file.#{creset}\n\n"
  warn e.message
  exit
end

vvv_config['hosts'] = [] unless vvv_config['hosts'].is_a? Hash
vvv_config['hosts'] += ['vvv.test']
vvv_config['sites'].each do |site, args|
  if args.is_a? String
    repo = args
    args = {}
    args['repo'] = repo
  end

  args = {} unless args.is_a? Hash

  defaults = {}
  defaults['repo'] = false
  defaults['vm_dir'] = "/srv/www/#{site}"
  defaults['local_dir'] = File.join(vagrant_dir, 'www', site)
  defaults['branch'] = 'master'
  defaults['skip_provisioning'] = false
  defaults['allow_customfile'] = false
  defaults['nginx_upstream'] = 'php'
  defaults['hosts'] = []

  vvv_config['sites'][site] = defaults.merge(args)

  unless vvv_config['sites'][site]['skip_provisioning']
    site_host_paths = Dir.glob(Array.new(4) { |i| vvv_config['sites'][site]['local_dir'] + '/*' * (i + 1) + '/vvv-hosts' })
    vvv_config['sites'][site]['hosts'] += site_host_paths.map do |path|
      lines = File.readlines(path).map(&:chomp)
      lines.grep(/\A[^#]/)
    end.flatten
    if vvv_config['sites'][site]['hosts'].is_a? Array
      vvv_config['hosts'] += vvv_config['sites'][site]['hosts']
    else
      vvv_config['hosts'] += ["#{site}.test"]
    end
  end
  vvv_config['sites'][site].delete('hosts')
end

if vvv_config['utility-sources'].is_a? Hash
  vvv_config['utility-sources'].each do |name, args|
    next unless args.is_a? String

    repo = args
    args = {}
    args['repo'] = repo
    args['branch'] = 'master'

    vvv_config['utility-sources'][name] = args
  end
else
  vvv_config['utility-sources'] = {}
end

unless vvv_config['utility-sources'].key?('core')
  vvv_config['utility-sources']['core'] = {}
  vvv_config['utility-sources']['core']['repo'] = 'https://github.com/Varying-Vagrant-Vagrants/vvv-utilities.git'
  vvv_config['utility-sources']['core']['branch'] = 'master'
end

vvv_config['utilities'] = {} unless vvv_config['utilities'].is_a? Hash
vvv_config['vm_config'] = {} unless vvv_config['vm_config'].is_a? Hash
vvv_config['general'] = {} unless vvv_config['general'].is_a? Hash

defaults = {}
defaults['memory'] = 4096
defaults['cores'] = 2
defaults['provider'] = 'virtualbox'
defaults['private_network_ip'] = '192.168.50.4'
vvv_config['vm_config'] = defaults.merge(vvv_config['vm_config'])
vvv_config['hosts'] = vvv_config['hosts'].uniq
vvv_config['vagrant-plugins'] = {} unless vvv_config['vagrant-plugins']

$vvv_config = vvv_config

if defined? vvv_config['vm_config']['provider']
  # Override or set the vagrant provider.
  ENV['VAGRANT_DEFAULT_PROVIDER'] = vvv_config['vm_config']['provider']
end

ENV['LC_ALL'] = 'en_US.UTF-8'

Vagrant.configure('2') do |config|
  # Store the current version of Vagrant for use in conditionals when dealing
  # with possible backward compatible issues.
  vagrant_version = Vagrant::VERSION.sub(/^v/, '')

  # Configurations from 1.0.x can be placed in Vagrant 1.1.x specs like the following.
  config.vm.provider :virtualbox do |v|
    v.customize ['modifyvm', :id, '--uartmode1', 'file', File.join(vagrant_dir, 'log/ubuntu-cloudimg-console.log')]
    v.customize ['modifyvm', :id, '--memory', vvv_config['vm_config']['memory']]
    v.customize ['modifyvm', :id, '--cpus', vvv_config['vm_config']['cores']]
    v.customize ['modifyvm', :id, '--natdnshostresolver1', 'on']
    v.customize ['modifyvm', :id, '--natdnsproxy1', 'on']
    v.customize ['modifyvm', :id, '--cableconnected1', 'on']
    v.customize ['modifyvm', :id, '--rtcuseutc', 'on']
    v.customize ['modifyvm', :id, '--audio', 'none']
    v.customize ['modifyvm', :id, '--paravirtprovider', 'kvm']
    v.customize ['modifyvm', :id, '--ostype', 'Ubuntu_64']
    v.customize ['setextradata', :id, 'VBoxInternal2/SharedFoldersEnableSymlinksCreate//srv/www', '1']
    v.customize ['setextradata', :id, 'VBoxInternal2/SharedFoldersEnableSymlinksCreate//srv/config', '1']
    v.name = 'vvv'
  end

  # Auto Download Vagrant plugins, supported from Vagrant 2.2.0
  unless Vagrant.has_plugin?('vagrant-hostsupdater') && Vagrant.has_plugin?('vagrant-goodhosts') && Vagrant.has_plugin?('vagrant-hostsmanager')
    if File.file?(File.join(vagrant_dir, 'vagrant-goodhosts.gem'))
      system('vagrant plugin install ' + File.join(vagrant_dir, 'vagrant-goodhosts.gem'))
      File.delete(File.join(vagrant_dir, 'vagrant-goodhosts.gem'))
      puts "#{yellow}VVV needed to install the vagrant-goodhosts plugin which is now installed. Please run the requested command again.#{creset}"
      exit
    else
      config.vagrant.plugins = ['vagrant-goodhosts']
    end
  end

  # The vbguest plugin has issues for some users, so we're going to disable it for now
  config.vbguest.auto_update = false if Vagrant.has_plugin?('vagrant-vbguest')
  config.ssh.forward_agent = true
  config.ssh.insert_key = false
  config.vm.box = 'bento/ubuntu-20.04'
  config.vm.box_check_update = false
  config.vm.hostname = 'vvv'

  if !vvv_config['vagrant-plugins']['disksize'].nil? && defined?(Vagrant::Disksize)
    config.vm.provider :virtualbox do |_v, override|
      override.disksize.size = vvv_config['vagrant-plugins']['disksize']
    end
  end

  config.vm.network :private_network, id: 'vvv_primary', ip: vvv_config['vm_config']['private_network_ip']
  config.vm.synced_folder '.', '/vagrant', disabled: true
  config.vm.provision 'file', source: "#{vagrant_dir}/config/.version", destination: '/home/vagrant/version'
  config.vm.synced_folder 'database/sql/', '/srv/database'
  use_db_share = false
  config.vm.synced_folder 'config/', '/srv/config'
  config.vm.synced_folder 'provision/', '/srv/provision'
  config.vm.synced_folder 'certificates/', '/srv/certificates', create: true
  config.vm.synced_folder 'log/nginx', '/var/log/nginx', owner: 'root', create: true, group: 'syslog', mount_options: ['dmode=777', 'fmode=666']
  config.vm.synced_folder 'log/php', '/var/log/php', create: true, owner: 'root', group: 'syslog', mount_options: ['dmode=777', 'fmode=666']
  config.vm.synced_folder 'log/provisioners', '/var/log/provisioners', create: true, owner: 'root', group: 'syslog', mount_options: ['dmode=777', 'fmode=666']
  config.vm.synced_folder 'www/', '/srv/www', owner: 'vagrant', group: 'www-data', mount_options: ['dmode=775', 'fmode=774']

  vvv_config['sites'].each do |site, args|
    next if args['skip_provisioning']
    if args['local_dir'] != File.join(vagrant_dir, 'www', site)
      config.vm.synced_folder args['local_dir'], args['vm_dir'], owner: 'vagrant', group: 'www-data', mount_options: ['dmode=775', 'fmode=774']
    end
  end

  config.vm.provision 'default', type: 'shell', keep_color: true, path: File.join('provision', '_provision'), env: { "VVV_LOG" => "main" }

  vvv_config['sites'].each do |site, args|
    next if args['skip_provisioning']
    config.vm.provision "site-#{site}", type: 'shell', keep_color: true, path: File.join('provision', '_provision-site'), args: [ site, args['repo'].to_s, args['branch'], args['vm_dir'], args['skip_provisioning'].to_s, args['nginx_upstream'] ], env: { "VVV_LOG" => "site-#{site}" }
  end

  if Vagrant.has_plugin?('vagrant-goodhosts')
    config.goodhosts.aliases = vvv_config['hosts']
    config.goodhosts.remove_on_suspend = true
  else
    show_check = true if %w[up halt resume suspend status provision reload].include? ARGV[0]
    if show_check
      puts ""
      puts " Run 'vagrant plugin install --local' to add hosts updater."
      puts ""
    end
  end

  config.trigger.after :up do |trigger|
    trigger.name = 'VVV Post-Up'
    trigger.run_remote = { inline: '/srv/config/homebin/vagrant_up' }
    trigger.on_error = :continue
  end
  config.trigger.before :reload do |trigger|
    trigger.name = 'VVV Pre-Reload'
    trigger.run_remote = { inline: '/srv/config/homebin/vagrant_halt' }
    trigger.on_error = :continue
  end
  config.trigger.after :reload do |trigger|
    trigger.name = 'VVV Post-Reload'
    trigger.run_remote = { inline: '/srv/config/homebin/vagrant_up' }
    trigger.on_error = :continue
  end
  config.trigger.before :halt do |trigger|
    trigger.name = 'VVV Pre-Halt'
    trigger.run_remote = { inline: '/srv/config/homebin/vagrant_halt' }
    trigger.on_error = :continue
  end
  config.trigger.before :suspend do |trigger|
    trigger.name = 'VVV Pre-Suspend'
    trigger.run_remote = { inline: '/srv/config/homebin/vagrant_suspend' }
    trigger.on_error = :continue
  end
  config.trigger.before :destroy do |trigger|
    trigger.name = 'VVV Pre-Destroy'
    trigger.run_remote = { inline: '/srv/config/homebin/vagrant_destroy' }
    trigger.on_error = :continue
  end
end
