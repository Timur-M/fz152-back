<?php

namespace App\Models;

class Balance extends Base
{
    public static function add_order($post){
        self::connect();

        $post['amount_pay'] = $post['amount'];
        $post['amount_set'] = $post['amount'];

        if(!empty($post['pack_152']) or !empty($post['pack_sec'])){
            $post['amount_pay'] = self::calc_discount($post);
            $post['amount_set'] = self::calc_discountless($post);
        }

        $stmt = static::$pdo->prepare("INSERT INTO payment_orders (user_id,amount_pay,amount_set,date_create,is_auto_pay) 
        VALUES (:user_id,:amount_pay,:amount_set,NOW() AT TIME ZONE 'UTC',:is_auto_pay) RETURNING id");
        $stmt->execute([
            $post['user_id'],
            $post['amount_pay'],
            $post['amount_set'],
            !empty($post['is_auto_pay']) ? $post['is_auto_pay'] : 0,
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
}