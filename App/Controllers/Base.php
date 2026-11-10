<?php

namespace App\Controllers;
use App\Models\Jwt;

class Base
{
    public static function post(){
        $json = json_decode(file_get_contents('php://input'), true) ?? [];
        $post = array_merge($json,$_POST);
        return $post;
    }

    public static function conf()
    {
        return require __DIR__ . '/../config/app.php';
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

    public static function check_jwt_get_user_id()
    {
        $post = self::post();
        $jwt_arr = Jwt::decrypt($post['jwt']);
        if($jwt_arr and is_array($jwt_arr) and !empty($jwt_arr['user_id'])){
            return $jwt_arr['user_id'];
        } else {
            return false;
        }
    }

}