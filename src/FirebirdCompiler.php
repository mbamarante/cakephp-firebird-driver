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
namespace CakephpFirebird;

use Cake\Database\QueryCompiler;

/**
 * Responsible for compiling a Query object into its SQL representation
 * for Firebird
 *
 * @internal
 */
class FirebirdCompiler extends QueryCompiler
{

    /**
     * {@inheritDoc}
     */
    protected $_templates = [
        'delete' => 'DELETE',
        'update' => 'UPDATE %s',
        'where' => ' WHERE %s',
        'group' => ' GROUP BY %s ',
        'having' => ' HAVING %s ',
        'order' => ' %s',
        'offset' => '',
        'epilog' => ' %s'
    ];

    /**
     * {@inheritDoc}
     */
    protected $_selectParts = [
        'select', 'from', 'join', 'where', 'group', 'having', 'order',
        'limit', 'union', 'offset', 'epilog'
    ];

    /**
     * @param array $parts
     * @param \Cake\Database\Query $query
     * @param \Cake\Database\ValueBinder $generator
     * @return string
     */
    protected function _buildSelectPart($parts, $query, $generator)
    {
        $driver = $query->connection()->driver();
        $select = 'SELECT %s%s%s';
        if ($this->_orderedUnion && $query->clause('union')) {
            $select = '(SELECT %s%s%s';
        }
        $distinct = $query->clause('distinct');
        $modifiers = $query->clause('modifier') ?: null;

        $normalized = [];
        $parts = $this->_stringifyExpressions($parts, $generator);

        foreach ($parts as $k => $p) {
            if (!is_numeric($k)) {
                $p = $p . ' AS "' . $k . '"';
            }
            $normalized[] = $p;
        }

        if ($distinct === true) {
            $distinct = 'DISTINCT ';
        }

        if (is_array($distinct)) {
            $distinct = $this->_stringifyExpressions($distinct, $generator);
            $distinct = sprintf('DISTINCT ON (%s) ', implode(', ', $distinct));
        }
        if ($modifiers !== null) {
            $modifiers = $this->_stringifyExpressions($modifiers, $generator);
            $modifiers = implode(' ', $modifiers) . ' ';
        }

        return sprintf($select, $distinct, $modifiers, implode(', ', $normalized));
    }

    /**
     * Generates the INSERT part of a SQL query
     *
     * To better handle concurrency and low transaction isolation levels,
     * we also include an OUTPUT clause so we can ensure we get the inserted
     * row's data back.
     *
     * @param array $parts The parts to build
     * @param \Cake\Database\Query $query The query that is being compiled
     * @param \Cake\Database\ValueBinder $generator the placeholder generator to be used in expressions
     * @return string
     */
    protected function _buildInsertPart($parts, $query, $generator)
    {
        $table = $parts[0];
        $columns = $this->_stringifyExpressions($parts[1], $generator);

        return sprintf('INSERT INTO %s (%s) ', $table, implode(', ', $columns));
    }

    /**
     * @param array $parts
     * @param \Cake\Database\Query $query
     * @param \Cake\Database\ValueBinder $generator
     * @return string
     */
    protected function _buildValuesPart($parts, $query, $generator)
    {
        $values = $parts[0];

        if (strpos( $parts[0], 'UNION ALL')) {
            $values = str_replace('(SELECT ', 'SELECT ', $values);
            $values = str_replace(')', ' FROM RDB$DATABASE', $values);
        }

        return trim($values);
    }

    /**
     * Generates the LIMIT part of a Firebird
     *
     * @param int $limit the limit clause
     * @param \Cake\Database\Query $query The query that is being compiled
     * @return string
     */
    protected function _buildLimitPart($limit, $query)
    {
        return false;
    }
}
