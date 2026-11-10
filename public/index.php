<?php 
error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/app_autoload.php';

$router = new \Bramus\Router\Router();

//$router->post('/api/get_users', '\App\Controllers\User@get_users');
$router->post('/jwt_check', '\App\Controllers\Jwt@check');
$router->post('/jwt_refresh', '\App\Controllers\Jwt@refresh');
$router->post('/login', '\App\Controllers\Jwt@login');
$router->post('/register', '\App\Controllers\Jwt@register');
$router->post('/approve', '\App\Controllers\Jwt@approve');
$router->post('/recover', '\App\Controllers\Jwt@recover');

$router->post('/api/{version}/{method}', '\App\Controllers\Api@main');


$router->run();