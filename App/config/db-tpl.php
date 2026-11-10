<?php
// --
return [
    'host' => 'localhost',
    'db' => 'ecoprod',
    'port' => 5432,
    'user' => 'timur', 
    'pass' => '366774', 
    'charset' => 'utf8',
    'opt' => [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
    ],
];

