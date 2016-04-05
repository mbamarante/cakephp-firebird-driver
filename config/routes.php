<?php
use Cake\Routing\Router;

Router::plugin(
    'CakephpFirebird',
    ['path' => '/cakephp-firebird'],
    function ($routes) {
        $routes->fallbacks('DashedRoute');
    }
);
