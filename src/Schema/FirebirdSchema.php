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
namespace CakephpFirebird\Schema;

use Cake\Database\Exception;
use Cake\Database\Schema\BaseSchema;
use Cake\Database\Schema\Table;
/**
 * Schema generation/reflection features for Firebird
 * Commands taken from http://www.alberton.info/firebird_sql_meta_info.html#.Vvv5wSbRJQt
 */
class FirebirdSchema extends BaseSchema
{

    /**
     * {@inheritDoc}
     */
    public function listTablesSql($config)
    {
        return [
            'SELECT RDB$RELATION_NAME
                  FROM RDB$RELATIONS
                  WHERE RDB$SYSTEM_FLAG=0;',
            []
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function describeColumnSql($tableName, $config)
    {
        return ['SELECT trim(LOWER(r.RDB$FIELD_NAME)) AS FIELD_NAME,
                    CASE f.RDB$FIELD_TYPE
                      WHEN 261 THEN \'blob\'
                      WHEN 14 THEN \'char(\' || f.RDB$FIELD_LENGTH || \')\'
                      WHEN 40 THEN \'cstring(\' || f.RDB$FIELD_LENGTH || \')\'
                      WHEN 11 THEN \'d_float(\' || f.RDB$FIELD_LENGTH || \')\'
                      WHEN 27 THEN \'double(\' || f.RDB$FIELD_LENGTH || \')\'
                      WHEN 10 THEN \'float(\' || f.RDB$FIELD_LENGTH || \')\'
                      WHEN 16 THEN \'int64(\' || f.RDB$FIELD_LENGTH || \')\'
                      WHEN 8 THEN \'integer(\' || f.RDB$FIELD_LENGTH || \')\'
                      WHEN 9 THEN \'quad\'
                      WHEN 7 THEN \'smallint(\' || f.RDB$FIELD_LENGTH || \')\'
                      WHEN 12 THEN \'date\'
                      WHEN 13 THEN \'time\'
                      WHEN 35 THEN \'timestamp\'
                      WHEN 37 THEN \'varchar(\' || f.RDB$FIELD_LENGTH || \')\'
                      ELSE \'UNKNOWN\'
                    END AS FIELD_TYPE,
                    r.RDB$NULL_FLAG AS FIELD_NULL,
                    r.RDB$DEFAULT_VALUE AS FIELD_DEFAULT,
                    coll.RDB$COLLATION_NAME AS FIELD_COLLATION,
                    r.RDB$DESCRIPTION AS FIELD_COMMENT,
                    f.RDB$FIELD_PRECISION AS field_precision,
                    f.RDB$FIELD_SCALE AS field_scale,
                    f.RDB$FIELD_SUB_TYPE AS field_subtype,
                    cset.RDB$CHARACTER_SET_NAME AS field_charset
                   FROM
                    RDB$RELATION_FIELDS r
                   LEFT JOIN RDB$FIELDS f ON r.RDB$FIELD_SOURCE = f.RDB$FIELD_NAME
                   LEFT JOIN RDB$COLLATIONS coll ON f.RDB$COLLATION_ID = coll.RDB$COLLATION_ID
                   LEFT JOIN RDB$CHARACTER_SETS cset ON f.RDB$CHARACTER_SET_ID = cset.RDB$CHARACTER_SET_ID
                  WHERE
                    LOWER(r.RDB$RELATION_NAME)=LOWER(\'' . $tableName . '\')
                  ORDER BY
                    r.RDB$FIELD_POSITION',
            []
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function describeIndexSql($tableName, $config)
    {
        return [
            'SELECT TRIM(i.RDB$INDEX_NAME) AS INDEX_NAME,
              i.RDB$INDEX_ID AS INDEX_ORDER,
              TRIM(rc.RDB$CONSTRAINT_NAME),
              TRIM(LOWER(s.RDB$FIELD_NAME)) AS COLUMN_NAME,
              CASE rc.RDB$CONSTRAINT_TYPE
                WHEN \'FOREIGN KEY\' THEN 0
                WHEN \'PRIMARY KEY\' THEN 1
              END AS is_primary_key,
              rc.RDB$CONSTRAINT_TYPE AS constraint_type,
              i.RDB$DESCRIPTION AS description,
              rc.RDB$DEFERRABLE AS is_deferrable,
              rc.RDB$INITIALLY_DEFERRED AS is_deferred,
              refc.RDB$UPDATE_RULE AS on_update,
              refc.RDB$DELETE_RULE AS on_delete,
              refc.RDB$MATCH_OPTION AS match_type,
              i2.RDB$RELATION_NAME AS references_table,
              s2.RDB$FIELD_NAME AS references_field,
              (s.RDB$FIELD_POSITION + 1) AS field_position
              FROM RDB$INDEX_SEGMENTS s
                LEFT JOIN RDB$INDICES i ON i.RDB$INDEX_NAME = s.RDB$INDEX_NAME
                LEFT JOIN RDB$RELATION_CONSTRAINTS rc ON rc.RDB$INDEX_NAME = s.RDB$INDEX_NAME
                LEFT JOIN RDB$REF_CONSTRAINTS refc ON rc.RDB$CONSTRAINT_NAME = refc.RDB$CONSTRAINT_NAME
                LEFT JOIN RDB$RELATION_CONSTRAINTS rc2 ON rc2.RDB$CONSTRAINT_NAME = refc.RDB$CONST_NAME_UQ
                LEFT JOIN RDB$INDICES i2 ON i2.RDB$INDEX_NAME = rc2.RDB$INDEX_NAME
                LEFT JOIN RDB$INDEX_SEGMENTS s2 ON i2.RDB$INDEX_NAME = s2.RDB$INDEX_NAME
              WHERE LOWER(i.RDB$RELATION_NAME)=LOWER(\'' . $tableName . '\')
                AND rc.RDB$CONSTRAINT_TYPE IS NOT NULL
                ORDER BY s.RDB$FIELD_POSITION',
         []
        ];
    }

    /**
     * Convert a Firebird column type into an abstract type.
     *
     * The returned type will be a type that Cake\Database\Type can handle.
     *
     * @param string $column The column type + length
     * @return array Array of column information.
     * @throws \Cake\Database\Exception When column type cannot be parsed.
     */
    protected function _convertColumn($column)
    {
        preg_match('/([a-z]+)(?:\(([0-9,]+)\))?\s*([a-z]+)?/i', $column, $matches);
        if (empty($matches)) {
            throw new Exception(sprintf('Unable to parse column type from "%s"', $column));
        }

        $col = strtolower($matches[1]);
        $length = $precision = null;
        if (isset($matches[2])) {
            $length = $matches[2];
            if (strpos($matches[2], ',') !== false) {
                list($length, $precision) = explode(',', $length);
            }
            $length = (int)$length;
            $precision = (int)$precision;
        }

        if (strpos($col, 'blob')) {
            return ['type' => 'binary', 'length' => $length];
        }
        if (in_array($col, ['date', 'time', 'timestamp'])) {
            return ['type' => $col, 'length' => null];
        }
        $unsigned = (isset($matches[3]) && strtolower($matches[3]) === 'unsigned');
        if (strpos($col, 'bigint') !== false || $col === 'bigint') {
            return ['type' => 'biginteger', 'length' => $length, 'unsigned' => $unsigned];
        }
        if (in_array($col, ['int', 'integer', 'tinyint', 'smallint', 'mediumint'])) {
            return ['type' => 'integer', 'length' => $length, 'unsigned' => $unsigned];
        }
        if ($col === 'char' && $length === 36) {
            return ['type' => 'uuid', 'length' => null];
        }
        if ($col === 'char') {
            return ['type' => 'string', 'fixed' => true, 'length' => $length];
        }
        if (strpos($col, 'char') !== false) {
            return ['type' => 'string', 'length' => $length];
        }
        if (strpos($col, 'text') !== false) {
            return ['type' => 'varchar', 'length' => 1000];
        }
        if (strpos($col, 'blob') !== false || $col === 'binary') {
            return ['type' => 'binary', 'length' => $length];
        }
        if (strpos($col, 'float') !== false || strpos($col, 'double') !== false) {
            return [
                'type' => 'float',
                'length' => $length,
                'precision' => $precision,
                'unsigned' => $unsigned
            ];
        }
        if (strpos($col, 'decimal') !== false) {
            return [
                'type' => 'decimal',
                'length' => $length,
                'precision' => $precision,
                'unsigned' => $unsigned
            ];
        }
        return ['type' => 'varchar', 'length' => 1000];
    }

    /**
     * {@inheritDoc}
     */
    public function convertColumnDescription(Table $table, $row)
    {
        $field = $this->_convertColumn($row['FIELD_TYPE']);
        $field += [
            'null' => $row['FIELD_NULL'] === '1' ? true : false,
            'default' => $row['FIELD_DEFAULT'],
            'collate' => $row['FIELD_COLLATION'],
            'comment' => $row['FIELD_COMMENT'],
        ];
        if (isset($row['FIELD_EXTRA']) && $row['FIELD_EXTRA'] === 'AUTO_INCREMENT') {
            $field['autoIncrement'] = true;
        }
        $table->addColumn(trim($row['FIELD_NAME']), $field);
    }

    /**
     * {@inheritDoc}
     */
    public function convertIndexDescription(Table $table, $row)
    {
        $type = null;
        $columns = $length = [];

        if ($row['IS_PRIMARY_KEY'] == '1') {
            $name = $type = Table::CONSTRAINT_PRIMARY;
        }

        $columns[] = trim($row['COLUMN_NAME']);

        if ($type === Table::CONSTRAINT_PRIMARY || $type === Table::CONSTRAINT_UNIQUE) {
            $table->addConstraint($name, [
                'type' => $type,
                'columns' => $columns
            ]);
            return;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function describeForeignKeySql($tableName, $config)
    {
        return ['SELECT DISTINCT
                  LOWER(rc.RDB$CONSTRAINT_NAME) AS "CONSTRAINT_NAME",
                  LOWER(rc.RDB$RELATION_NAME) AS "ON_TABLE",
                  LOWER(d1.RDB$FIELD_NAME) AS "COLUMN_NAME",
                  LOWER(d2.RDB$DEPENDED_ON_NAME) AS "REFERENCED_TABLE_NAME",
                  LOWER(d2.RDB$FIELD_NAME) AS "REFERENCED_COLUMN_NAME",
                  LOWER(refc.RDB$UPDATE_RULE) AS "UPDATE_RULE",
                  LOWER(refc.RDB$DELETE_RULE) AS "DELETE_RULE"
                  FROM RDB$RELATION_CONSTRAINTS AS rc
                    LEFT JOIN RDB$REF_CONSTRAINTS refc ON rc.RDB$CONSTRAINT_NAME = refc.RDB$CONSTRAINT_NAME
                    LEFT JOIN RDB$DEPENDENCIES d1 ON d1.RDB$DEPENDED_ON_NAME = rc.RDB$RELATION_NAME
                    LEFT JOIN RDB$DEPENDENCIES d2 ON d1.RDB$DEPENDENT_NAME = d2.RDB$DEPENDENT_NAME
                  WHERE rc.RDB$CONSTRAINT_TYPE = \'FOREIGN KEY\'
                    AND d1.RDB$DEPENDED_ON_NAME <> d2.RDB$DEPENDED_ON_NAME
                    AND d1.RDB$FIELD_NAME <> d2.RDB$FIELD_NAME
                    AND LOWER(rc.RDB$RELATION_NAME) = LOWER(\'' . $tableName . '\')',
            [],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function convertForeignKeyDescription(Table $table, $row)
    {
        $data = [
            'type' => Table::CONSTRAINT_FOREIGN,
            'columns' => [trim($row['COLUMN_NAME'])],
            'references' => [trim($row['REFERENCED_TABLE_NAME']), trim($row['REFERENCED_COLUMN_NAME'])],
            'update' => $this->_convertOnClause(trim($row['UPDATE_RULE'])),
            'delete' => $this->_convertOnClause(trim($row['DELETE_RULE'])),
        ];
        $name = $row['CONSTRAINT_NAME'];
        $table->addConstraint($name, $data);
    }

    /**
     * {@inheritDoc}
     */
    public function truncateTableSql(Table $table)
    {
        $sql = sprintf("DELETE FROM %s", strtoupper($table->name()));
        return [$sql];
    }

    /**
     * {@inheritDoc}
     */
    public function columnSql(Table $table, $name)
    {
        $data = $table->getColumn($name);
        $out = $this->_driver->quoteIdentifier($name);
        $typeMap = [
            'integer' => ' INTEGER',
            'biginteger' => ' BIGINT',
            'boolean' => ' BOOLEAN',
            'binary' => ' LONGBLOB',
            'float' => ' FLOAT',
            'decimal' => ' DECIMAL',
            'text' => ' VARCHAR(1000)',
            'date' => ' DATE',
            'time' => ' TIME',
            'datetime' => ' DATETIME',
            'timestamp' => ' TIMESTAMP',
            'uuid' => ' CHAR(36)',
        ];
        $specialMap = [
            'string' => true,
        ];
        if (isset($typeMap[$data['type']])) {
            $out .= $typeMap[$data['type']];
        }
        if (isset($specialMap[$data['type']])) {
            switch ($data['type']) {
                case 'string':
                    $out .= !empty($data['fixed']) ? ' CHAR' : ' VARCHAR';
                    if (!isset($data['length'])) {
                        $data['length'] = 255;
                    }
                    break;
            }
        }
        $hasLength = ['integer', 'string'];
        if (in_array($data['type'], $hasLength, true) && isset($data['length'])) {
            $out .= '(' . (int)$data['length'] . ')';
        }

        $hasPrecision = ['float', 'decimal'];
        if (in_array($data['type'], $hasPrecision, true) &&
            (isset($data['length']) || isset($data['precision']))
        ) {
            $out .= '(' . (int)$data['length'] . ',' . (int)$data['precision'] . ')';
        }

        $hasUnsigned = ['float', 'decimal', 'integer', 'biginteger'];
        if (in_array($data['type'], $hasUnsigned, true) &&
            isset($data['unsigned']) && $data['unsigned'] === true
        ) {
            $out .= ' UNSIGNED';
        }

        if (isset($data['null']) && $data['null'] === false) {
            $out .= ' NOT NULL';
        }
        if (isset($data['null']) && $data['null'] === true) {
            $out .= $data['type'] === 'timestamp' ? ' NULL' : ' DEFAULT NULL';
            unset($data['default']);
        }
        if (isset($data['default']) && !in_array($data['type'], ['timestamp', 'datetime'])) {
            $out .= ' DEFAULT ' . $this->_driver->schemaValue($data['default']);
            unset($data['default']);
        }
        if (isset($data['default']) &&
            in_array($data['type'], ['timestamp', 'datetime']) &&
            strtolower($data['default']) === 'current_timestamp'
        ) {
            $out .= ' DEFAULT CURRENT_TIMESTAMP';
            unset($data['default']);
        }
        if (isset($data['comment']) && $data['comment'] !== '') {
            $out .= ' COMMENT ' . $this->_driver->schemaValue($data['comment']);
        }
        return $out;
    }

    /**
     */
    public function createTableSql(Table $table, $columns, $constraints, $indexes)
    {
        $content = array_merge($columns, $constraints);
        $content = implode(",\n", array_filter($content));
        $tableName = $this->_driver->quoteIdentifier($table->name());
        $out = [];
        $out[] = sprintf("CREATE TABLE %s (\n%s\n)", strtoupper($tableName), strtoupper($content));
        foreach ($indexes as $index) {
            $out[] = $index;
        }
        return $out;
    }

    /**
     * {@inheritDoc}
     */
    public function constraintSql(Table $table, $name)
    {
        $data = $table->getConstraint($name);
        if ($data['type'] === Table::CONSTRAINT_PRIMARY) {
            return sprintf('CONSTRAINT pk_%s_0 PRIMARY KEY ("%s")', $table->name(), implode(', ', $data['columns']));
        }

        return false;
    }

    /**
     * @param Table $table
     * @return array
     */
    public function  dropTableSql(Table $table)
    {
        $sql = sprintf(
            'DROP TABLE %s',
            strtoupper(
                $this->_driver->quoteIdentifier($table->name())
            )
        );
        return [$sql];
    }

    /**
     * {@inheritDoc}
     */
    public function addConstraintSql(Table $table)
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function dropConstraintSql(Table $table)
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function indexSql(Table $table, $name)
    {
        return false;
    }

    /**
     * Helper method for generating key SQL snippets.
     *
     * @param string $prefix The key prefix
     * @param array $data Key data.
     * @return string
     */
    protected function _keySql($prefix, $data)
    {
        return false;
    }
}