<?php

namespace App\Models;

class Balance extends Base
{
    public static function add_order($post){
        self::connect();

        if(!empty($post['pack_152']) or !empty($post['pack_sec'])){
            $post['amount'] = self::calc_discountless($post);
            if(!empty($post['pack_152'])){
                $qtys['pack_152'] = $post['pack_152'];
            }
            if(!empty($post['pack_sec'])){
                $qtys['pack_sec'] = $post['pack_sec'];
            }
            $qtys = json_encode($qtys);
        }

        $stmt = static::$pdo->prepare("INSERT INTO payment_orders (user_id,amount,date_create,is_auto_pay,qtys) 
        VALUES (:user_id,:amount,NOW() AT TIME ZONE 'UTC',:is_auto_pay,:qtys) RETURNING id");
        $stmt->execute([
            'user_id' => $post['user_id'],
            'amount' => $post['amount'],
            'is_auto_pay' => !empty($post['is_auto_pay']) ? $post['is_auto_pay'] : 0,
            'qtys' => !empty($qtys) ? $qtys : NUll,
        ]);
        $result = $stmt->fetch();
        return $result;
    }    

    public static function calc_discount($post){
        $prices = self::get_prices();
        $discounts = self::get_discounts();
        $sum_152 = 0;
        $sum_sec = 0;
        if(!empty($post['pack_152'])){
            $discountless_152 = $prices['152'] * $post['pack_152'];
            $sum_152 = $discountless_152 - ($discountless_152/100*$discounts['152'][$post['pack_152']]);
        }
        if(!empty($post['pack_sec'])){
            $discountless_sec = $prices['sec'] * $post['pack_sec'];
            $sum_sec = $discountless_sec - ($discountless_sec/100*$discounts['sec'][$post['pack_sec']]);
        }
        return $sum_152 + $sum_sec;
    }

    public static function calc_discountless($post){
        $prices = self::get_prices();
        $sum = 0;
        if(!empty($post['pack_152'])){
            $sum += $prices['152'] * $post['pack_152'];
        }
        if(!empty($post['pack_sec'])){
            $sum += $prices['sec'] * $post['pack_sec'];
        }
        return $sum;
    }

    public static function get_prices(){
        self::connect();
        $stmt = static::$pdo->prepare("SELECT * FROM prices");
        $stmt->execute([]);
        $result = $stmt->fetchAll();
        foreach($result as $r){
            $prices[$r['price_key']] = $r['price_value'];
        }
        return $prices;
    }    

    public static function get_discounts(){
        self::connect();
        $stmt = static::$pdo->prepare("SELECT * FROM discounts");
        $stmt->execute([]);
        $result = $stmt->fetchAll();
        foreach($result as $r){
            $discounts[$r['d_type']][$r['qty']] = $r['percent'];
        }
        return $discounts;
    }  

    public static function get_orders($post){
        self::connect();
        $stmt = static::$pdo->prepare("SELECT *, 
        to_char((date_create + interval '3 hours'),'DD-MM-YYYY HH24:MI') as date_create,
        CASE WHEN date_update IS NOT NULL THEN
        to_char((date_update + interval '3 hours'),'DD-MM-YYYY HH24:MI') 
        ELSE NULL END as date_update
        FROM payment_orders WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $post['user_id']]);
        $result = $stmt->fetchAll();
        return $result;
    }
}