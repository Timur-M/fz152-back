<?php

namespace App\Models;

class User extends Base
{
    public static function get_user($id){
        self::connect();
        $stmt = static::$pdo->prepare("SELECT id,inn,phone,org_name,balance,qtys FROM users WHERE id=:id");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result;
    }    

    public static function update_user($post){
        self::connect();
        $stmt = static::$pdo->prepare("UPDATE users SET inn=:inn,phone=:phone,org_name=:org_name WHERE id=:id");
        $stmt->execute([
            'id' => $post['user_id'],
            'inn' => $post['inn'],
            'phone' => $post['phone'],
            'org_name' => $post['org_name'],
        ]);
        $result = $stmt->fetch();
        return $result;
    }  
}