<?php 
error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/app_autoload.php';

$router = new \Bramus\Router\Router();

$_SERVER['SERVER_PROTOCOL'] = 'HTTP';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/'.$argv[1];

$router->get('/cron_work', '\App\Controllers\Cron@work');

$router->run();