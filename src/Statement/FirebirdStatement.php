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
namespace CakephpFirebird\Statement;

use PDO;
use Cake\Database\Statement\PDOStatement;
use Cake\Database\Statement\BufferResultsTrait;

/**
 * Statement class meant to be used by a Firebird PDO driver
 *
 * @internal
 */
class FirebirdStatement extends PDOStatement
{

    use BufferResultsTrait;

    /**
     * {@inheritDoc}
     *
     */
    public function execute($params = null)
    {
        $result = $this->_statement->execute($params);
        return $result;
    }

    /**
     * @return int
     */
    public function rowCount()
    {
        if (
            strpos($this->_statement->queryString, 'INSERT') === 0 ||
            strpos($this->_statement->queryString, 'UPDATE') === 0 ||
            strpos($this->_statement->queryString, 'DELETE') === 0
        ) {
            return ($this->errorCode() == '00000' ? 1 : 0);
        }

        $count = count($this->_statement->fetchAll());
        $this->execute(); // kind of rewind...
        return $count;
    }

}
