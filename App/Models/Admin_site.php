<?php

namespace App\Models;

class Admin_site extends Base
{
    

    public static function get_sites(array $post = []){
        self::connect();
        $stmt = static::$pdo->prepare("SELECT s.*, u.email as user_email,
            to_char((s.date_add + interval '3 hours'),'DD-MM-YYYY HH24:MI') as date_add
            FROM sites s
            LEFT JOIN users u ON s.user_id = u.id
            ORDER BY id");
        $stmt->execute([]);
        $result = $stmt->fetchAll();
        return $result;
    }

    
}