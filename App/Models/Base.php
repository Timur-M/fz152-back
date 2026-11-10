<?php

namespace App\Models;

class Base
{
    public static $pdo;

    public static function connect()
    {
        $db_config = require __DIR__ . '/../config/db.php';
        $dsn = "pgsql:host=".$db_config['host'].";port=".$db_config['port'].";dbname=".$db_config['db'];
        self::$pdo = new \PDO($dsn, $db_config['user'], $db_config['pass'], $db_config['opt']);
    }

    public static function log($e,$type = 'error')
    {
        if($type == 'error'){
            $dt = new \DateTime("now", new \DateTimeZone('Europe/Moscow')); 
            $error  = '------------------------------------------------------------------'."\r\n";
            $error .= $dt->format('d.m.Y, H:i:s').' (МСК)'."\r\n";
            $error .= $e->getMessage()."\r\n";
            $error .= 'in file: '.$e->getFile()."\r\n";
            $error .= 'at line: '.$e->getLine();
            @file_put_contents(__DIR__.'/../../public/error.log',$error."\r\n",FILE_APPEND);
        } else {
            @file_put_contents(__DIR__.'/../../public/'.$type.'.log',json_encode($e, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\r\n",FILE_APPEND);
        }
    }

    public static function conf()
    {
        return require __DIR__ . '/../config/app.php';
    }
}