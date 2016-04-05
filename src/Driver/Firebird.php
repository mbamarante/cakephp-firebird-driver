<?php
/**
 * Copyright 2016 Maicon Amarante
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2016 Maicon Amarante
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace CakephpFirebird\Driver;

use PDO;
use Cake\Database\Driver;
use Cake\Database\Query;
use Cake\Database\Driver\PDODriverTrait;
use CakephpFirebird\Dialect\FirebirdDialectTrait;
use CakephpFirebird\Schema\FirebirdSchema;
use CakephpFirebird\Statement\FirebirdStatement;

class Firebird extends Driver
{
    use PDODriverTrait;
    use FirebirdDialectTrait;

    /**
     * Base configuration settings for Firebird driver
     *
     * @var array
     */
    protected $_baseConfig = [
        'persistent' => true,
        'host' => 'localhost',
        'username' => 'sysdba',
        'password' => 'masterkey',
        'database' => '/data/cake.fdb',
        'port' => '3050',
        'flags' => [],
        'encoding' => 'utf8',
        'timezone' => null,
        'init' => [],
    ];

    /**
     * Establishes a connection to the database server
     *
     * @return bool true on success
     */
    public function connect()
    {
        if ($this->_connection) {
            return true;
        }

        $config = $this->_config;
        $config['flags'] += [
            PDO::FB_ATTR_TIMESTAMP_FORMAT => '%s',
        ];

        $dsn = "firebird:dbname={$config['host']}/{$config['port']}:{$config['database']};charset={$config['encoding']}";
        $this->_connect($dsn, $config);

        if (!empty($config['init'])) {
            $connection = $this->connection();
            foreach ((array)$config['init'] as $command) {
                $connection->exec($command);
            }
        }

        return true;
    }

    /**
     * Returns whether php is able to use this driver for connecting to database
     *
     * @return bool true if it is valid to use this driver
     */
    public function enabled()
    {
        return in_array('firebird', PDO::getAvailableDrivers());
    }

    /**
     * {@inheritDoc}
     *
     * @return \CakephpFirebird\Schema\FirebirdSchema
     */
    public function schemaDialect()
    {
        if (!$this->_schemaDialect) {
            $this->_schemaDialect = new FirebirdSchema($this);
        }
        return $this->_schemaDialect;
    }

    /**
     * Prepares a sql statement to be executed
     *
     * @param string|\Cake\Database\Query $query The query to prepare.
     * @return \Cake\Database\StatementInterface
     */
    public function prepare($query)
    {
        $this->connect();
        $isObject = $query instanceof Query;
        $statement = $this->_connection->prepare($isObject ? $query->sql() : $query);
        return new FirebirdStatement($statement, $this);
    }

    /**
     * Returns whether the driver supports adding or dropping constraints
     * to already created tables.
     *
     * @return bool true if driver supports dynamic constraints
     */
    public function supportsDynamicConstraints()
    {
        return false;
    }

    /**
     * autoinc has to be defined inside firebird db (generators/trigger)
     * becouse firebird PDO doesn't implements lastInsertId()
     *
     * @param null $table
     * @param null $column
     * @return int|string
     */
    public function lastInsertId($table = null, $column = null)
    {
        return false;
    }

    /**
     * @return string
     */
    public function disableForeignKeySQL()
    {
        return 'select \'false\' from rdb$database';
    }

    /**
     * @return string
     */
    public function enableForeignKeySQL()
    {
        return 'select \'false\' from rdb$database';
    }

    /**
     * @return bool
     */
    public function isConnected()
    {
        if ($this->_connection === null) {
            $connected = false;
        } else {
            try {
                $connected = $this->_connection->query('select current_timestamp from rdb$database');
            } catch (\PDOException $e) {
                $connected = false;
            }
        }
        $this->connected = !empty($connected);
        return $this->connected;
    }
}
