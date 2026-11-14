<?php

namespace App\Models;

class Admin_price extends Base
{
    public static function get_all(array $post = []){
        self::connect();
        $stmt = static::$pdo->prepare("SELECT * FROM prices");
        $stmt->execute([]);
        foreach($stmt->fetchAll() as $p){
            $result['prices'][$p['price_key']] = $p['price_value'];
        }
        $stmt = static::$pdo->prepare("SELECT * FROM discounts ORDER BY d_type,qty");
        $stmt->execute([]);
        $result['discounts'] = $stmt->fetchAll();
        return $result;
    }   

    public static function update_all(array $post = []){
        self::connect();
        foreach($post['prices'] as $price_key => $price_value){
            $stmt = static::$pdo->prepare("UPDATE prices SET price_value=:v WHERE price_key=:k");
            $stmt->execute([
                'v' => $price_value,
                'k' => $price_key,
            ]);
        }
        foreach($post['discounts'] as $d){
            if($d['id'] > 0){
                $stmt = static::$pdo->prepare("UPDATE discounts SET qty=:q, percent=:p WHERE id=:id");
                $stmt->execute([
                    'q' => $d['qty'],
                    'p' => $d['percent'],
                    'id' => $d['id'],
                ]);
            } else {
                $stmt = static::$pdo->prepare("INSERT INTO discounts (d_type,qty,percent) VALUES (:t,:q,:p)");
                $stmt->execute([
                    'q' => $d['qty'],
                    'p' => $d['percent'],
                    't' => $d['d_type'],
                ]);
            }
        }
        return true;
    }  

}