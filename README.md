![Universidade Aberta](public/assets/img/UAb.svg )

# UAb SimpleSAMLphp Module <!-- omit in toc -->

[![release](https://labs.si.uab.pt/dsi/simplesamlphp-module-uab/-/badges/release.svg)](https://labs.si.uab.pt/dsi/simplesamlphp-module-uab/-/releases/permalink/latest) [![License: GPL v3+](https://img.shields.io/badge/License-GPL%20v3%2B-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)


This module provides UAb customizations for SimpleSAMLphp.

## Dependencies

### OS Dependencies

Depending of your goals and configuration you will need some packages installed on your OS. The following command lists some of the packages we use on our setup (based on Ubuntu Server). Adjust accordingly: 
```bash
apt install nginx openssl \
	mariadb-server mariadb-client \
	php-fpm php-cli php-gd php-zip php-bcmath php-curl php-imagick php-xml php-mbstring php-xml php-intl \
    php-mysql php-ldap php-redis php-predis php-memcached memcached \
    git curl ca-certificates unzip
```

### SimpleSAMLphp

This module is compatible with SimpleSAMLphp 2.0 (RC 3 as the time of this info). If you want to use the development branch `simplesamlphp-2.0` of SimpleSAMLphp, you can to it with the following command: 
```bash
git clone -b simplesamlphp-2.0 --single-branch -o upstream https://github.com/simplesamlphp/simplesamlphp.git
```

If you want to use the development branch, you should also set the version in your composer file with the following command: 
```bash
composer config version v2.0.0
composer update --no-dev
```

This will help to install the correct version of the module dependencies. 

### LDAP

LDAP is a soft dependency, which means you probably can configure your instance to work without it. However in our implementation we use it, so you can install it with the following command: 
```bash
composer require --no-dev simplesamlphp/simplesamlphp-module-ldap
```
Please refer to [module documentation](https://github.com/simplesamlphp/simplesamlphp-module-ldap) for more information about the module requirements and settings.


### CAS Server

CAS Server is a soft dependency, which means you probably can configure your instance to work without it. However in our implementation we use it, so you can install it with the following command: 
```bash
composer config repositories.repo-name vcs https://github.com/universidade-aberta/simplesamlphp-module-casserver.git
composer require simplesamlphp/simplesamlphp-module-casserver dev-master
```
Please refer to [module documentation](https://github.com/universidade-aberta/simplesamlphp-module-casserver) for more information about the module requirements and settings.



## Installation

Once you have installed SimpleSAMLphp, installing this module is very simple.
Just execute the following command in the root of your SimpleSAMLphp
installation:

```bash
composer config repositories.repo-name vcs ssh://git@labs.si.uab.pt:2222/dsi/simplesamlphp-module-uab.git
composer require --no-dev uab/simplesamlphp-module-uab:dev-master
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

### Database Installation

#### Base Database
You can create a MySQL database to store auxiliary information. Example command to create a database named `auth_db` and an user `auth` with access to it: 
```sql
    CREATE DATABASE IF NOT EXISTS `auth_db`;
    CREATE USER /*M!100103 IF NOT EXISTS */ "auth"@"localhost" IDENTIFIED BY "__pwd__";
    GRANT ALL ON `auth_db`.* TO "auth"@"localhost" WITH GRANT OPTION;
    FLUSH PRIVILEGES;

    USE `auth_db`;
```

#### Table for Autenticação.gov
If you want to match a Autenticação.gov attribute with an attribute of your internal IDP (e.g. LDAP), you will need the create a MySQL table to store this association: 
```sql
    CREATE TABLE IF NOT EXISTS `uab_user_attributes_matching__tbl` (
        `identity_ID` bigint(20) DEFAULT NULL COMMENT 'Refers to a common ID (if aplicable)',
        `auth_source_primary` varchar(50) DEFAULT 'ldap' COMMENT '1st attribute source',
        `auth_source_primary_match_field` varchar(50) DEFAULT 'sAMAccountName' COMMENT '1st attribute to match',
        `auth_source_primary_match_value` varchar(150) NOT NULL COMMENT '1st attribute value to match',
        `auth_source_secondary` varchar(50) NOT NULL DEFAULT 'autenticacao_gov' COMMENT '2nd attribute source',
        `auth_source_secondary_match_field` varchar(50) NOT NULL DEFAULT 'NIF' COMMENT '2nd attribute to match',
        `auth_source_secondary_match_value` varchar(150) NOT NULL COMMENT '2nd attribute to match',
        `_inserted` datetime NOT NULL DEFAULT current_timestamp(),
        `_last_update` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        UNIQUE KEY `unique_id_map` (`identity_ID`,`auth_source_primary`,`auth_source_primary_match_field`,`auth_source_primary_match_value`,`auth_source_secondary`,`auth_source_secondary_match_field`,`auth_source_secondary_match_value`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Associate attributes of multiple IDP identities';
```

The processing filter `uab:usermatch` will use this table to match and associate two identities, for example a `NIF` from Autenticação.gov with `sAMAccountName` of a LDAP server. By using this processing filter, when a user autenticates using Autenticação.gov, if match for `NIF` is found with the respective `sAMAccountName`, the user is authenticated; however, if no match is found the system asks the user to authenticate with his/her LDAP credentials to associate both accounts for future usage. If you don't want this functionality, you will not need this requirement. 

## Updates

`composer update` whenever there is a new release of the framework or UAb module.

## Configuration

An initial demo configuration is provided in the `config/dist.*` and  `metadata/dist.*`. This configuration is used in the docker container but it is provided as a reference (and probably won't work without some changes - e.g. LDAP server settings, SAML endpoints, etc.). You should adjust your SimpleSAMLphp configuration accordingly and consult the framework's documentation for reference. 

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
