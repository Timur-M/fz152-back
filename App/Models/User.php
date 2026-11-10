<?php

namespace App\Models;

class User extends Base
{
    public static function get_user($id){
        self::connect();
        $stmt = static::$pdo->prepare("SELECT * FROM users WHERE id=:id");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result;
    }    

}