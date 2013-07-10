<?php

require_once __DIR__ . '../../vendor/autoload.php';

use Respect\Rest\Router;

$r = new Router();

$r->any('/**', function ($url) {
    return 'Welcome to Respect/Rest the url you want is: /'.implode('/', $url);
});