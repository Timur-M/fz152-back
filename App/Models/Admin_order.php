<?php

namespace App\Models;

class Admin_order extends Base
{
    public static function get_orders(array $post = []){
        self::connect();
        $stmt = static::$pdo->prepare("SELECT o.*,u.email as user_email,
        to_char((o.date_time + interval '3 hours'),'DD-MM-YYYY HH24:MI') as date_time
         FROM payment_orders o 
         LEFT JOIN users u on o.user_id = u.id
         WHERE 1=1");
        $stmt->execute([]);
        $result = $stmt->fetchAll();
        return $result;
    }    

}