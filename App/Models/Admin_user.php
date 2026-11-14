<?php

namespace App\Models;

class Admin_user extends Base
{
    public static function get_users(array $post = []){
        self::connect();
        $stmt = static::$pdo->prepare("SELECT u.*,
        to_char((u.date_add + interval '3 hours'),'DD-MM-YYYY HH24:MI') as date_add
         FROM users u WHERE 1=1");
        $stmt->execute([]);
        $result = $stmt->fetchAll();
        return $result;
    }    

}