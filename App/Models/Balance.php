<?php

namespace App\Models;

class Balance extends Base
{
    public static function add_balance($post){
        self::connect();

        if(!empty($post['pack_152']) or !empty($post['pack_sec'])){
            $post['amount'] = self::calc_discount($post);
        }


        $stmt = static::$pdo->prepare("INSERT INTO payment_orders (amount,date_time,site_id,period,
        is_auto_pay,pack_152,pack_sec,description) VALUES (:amount,NOW() AT TIME ZONE 'UTC',:site_id,:period,
        :is_auto_pay,:pack_152,:pack_sec,:description) RETURNING id");
        $stmt->execute([
            $post['amount'],
            !empty($post['site_id']) ? $post['site_id'] : 0,
            !empty($post['period']) ? $post['period'] : '',
            !empty($post['is_auto_pay']) ? $post['is_auto_pay'] : 0,
            !empty($post['pack_152']) ? $post['pack_152'] : 0,
            !empty($post['pack_sec']) ? $post['pack_sec'] : 0,
            'Заказ'
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
            $discountless_152 = $prices['once_152'] * $post['pack_152'];
            $sum_152 = $discountless_152 - ($discountless_152/100*$discounts['152'][$post['pack_152']]);
        }
        if(!empty($post['pack_sec'])){
            $discountless_sec = $prices['once_sec'] * $post['pack_sec'];
            $sum_sec = $discountless_sec - ($discountless_sec/100*$discounts['sec'][$post['pack_sec']]);
        }
        return $sum_152 + $sum_sec;
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