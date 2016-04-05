<?php
$findRoot = function ($root) {
    do {
        $lastRoot = $root;
        $root = dirname($root);
        if (is_dir($root . '/vendor/cakephp/cakephp')) {
            return $root;
        }
    } while ($root !== $lastRoot);
    throw new Exception('Cannot find the root of the application, unable to run tests');
};
$root = $findRoot(__FILE__);
unset($findRoot);
chdir($root);

if (!getenv('cakephpfirebird_dsn')) {
    putenv('cakephpfirebird_dsn=Cake\Database\Connection://127.0.0.1:3050//path-to-database/database.fdb?charset=ISO8859_1&username=sysdba&password=masterkey&driver=CakephpFirebird\Driver\Firebird');
}

require $root . '/vendor/cakephp/cakephp/tests/bootstrap.php';

use Cake\Datasource\ConnectionManager;

ConnectionManager::config('test_cakephpfirebird', ['url' => getenv('cakephpfirebird_dsn')]);