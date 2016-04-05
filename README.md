# CakePHP 3 Driver for Firebird Database

Currently provides data reading, inserting, deleting and updating.

## Installation

You can install this plugin into your CakePHP application using [composer](http://getcomposer.org).

The recommended way to install composer packages is:

```
composer require mbamarante/cakephp-firebird
```

In bootstrap.php load plugin.

```
Plugin::load('mbamarante/cakephp-firebird');
```

## Requirements

- CakePHP 3.2+
- an Firebird PHP extension
    - For Ubuntu 14.04 installing see [Ubuntu-PDO](docs/UbuntuPDO.md)

## Datasource configuration

Here is an example datasource configuration:

```
'myfbconnection' => [
    'className' => 'Cake\Database\Connection',
    'driver' => 'CakephpFirebird\Driver\Firebird',
    'host' => '127.0.0.1',
    'port' => '3050',
    'username' => 'sysdba',
    'password' => 'masterkey',
    'database' => '/path-to-database/database.fdb',
    ]
```