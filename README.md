![Universidade Aberta](public/assets/img/UAb.svg )

# UAb SimpleSAMLphp Module <!-- omit in toc -->

[![release](https://labs.si.uab.pt/dsi/simplesamlphp-module-uab/-/badges/release.svg)](https://labs.si.uab.pt/dsi/simplesamlphp-module-uab/-/releases/permalink/latest) [![License: GPL v3+](https://img.shields.io/badge/License-GPL%20v3%2B-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)


This module provides UAb customizations for SimpleSAMLphp.


## Installation

Once you have installed SimpleSAMLphp, installing this module is very simple.
Just execute the following command in the root of your SimpleSAMLphp
installation:

```bash
composer config repositories.repo-name vcs ssh://git@labs.si.uab.pt:2222/dsi/simplesamlphp-module-uab.git
composer require uab/simplesamlphp-module-uab:dev-master
```

where `dev-master` instructs Composer to install the `master` branch from the
Git repository. See the releases available if you want to use a stable version of the module. 

Please note that you may need to generate an SSH key, add the public key in the VCS to be able to access the repository and configure the (client) server to be able to connect to VCS using the custom SSH keys. You can add use the keys in the `$HOME/.ssh/` or use a custom configuration for composer ([https://getcomposer.org/doc/articles/handling-private-packages.md#secur](https://getcomposer.org/doc/articles/handling-private-packages.md#secur)). 

Next thing you need to do is to enable the module: in `config.php`,
search for the `module.enable` key and set `uab` to true:

```php
    'module.enable' => [
         'uab' => true,
         …
    ],
```
## Updates

`composer update` whenever there is a new release of the framework or UAb module.

## Configuration

An initial demo configuration is provided in the `config/*.dist` and  `metadata/*.dist`. This configuration is used in the docker container but it is provided as a reference (and probably won't work without some changes - e.g. LDAP server settings, SAML endpoints, etc.). You should adjust your SimpleSAMLphp configuration accordingly and consult the framework's documentation for reference. 

## Development instructions

### Development environment

This project can be tested with `docker` and `docker-composer`.
Assuming the ports 80 and 443 of your machine are not being used by another service, run the following command on the theme folder and visit the address `http://localhost` in your browser.
```console
docker-compose up
```

The default credentials are set in the `.env` file. 

### Accessing the database

The database can be accessed using phpMyAdmin [http://localhost/phpmyadmin](http://localhost/phpmyadmin) with the username `test_db_user` and password `test_db_pwd`. You can also use the CLI:

```console
docker exec -it simplesamlphp-module-uab mysql
```

### Running commands inside the container

If required, the developer can execute commands inside the container by using the following command:

```console
docker exec -it simplesamlphp-module-uab bash
```
