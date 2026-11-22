<?php

namespace App\Controllers;

use App\Models\Admin_site;
use App\Models\Admin_user;
use App\Models\Admin_order;
use App\Models\Admin_price;



class admin_api extends Base
{
    
    public static function main($version, $method){
        $user_id = self::check_jwt_get_user_id();
        if($user_id){
            echo json_encode(self::$method(['api_version'=>$version,'user_id'=>$user_id]));
        } else {
            echo json_encode(['status' => 'error','error_text' => 'bad_jwt']);
        }
    }

    public static function get_sites($main_data){
        $post = self::post();
        $result = Admin_site::get_sites($post);
        return ['status' => 'ok', 'result' => $result];
    }

    public static function get_users($main_data){
        $post = self::post();
        $users = Admin_user::get_users($post);
        return ['status' => 'ok', 'result' => $users];
    }

    public static function get_orders($main_data){
        $post = self::post();
        $orders = Admin_order::get_orders();
        return ['status' => 'ok', 'orders' => $orders];
    }

    public static function get_prices_and_discounts($main_data){
        $post = self::post();
        $all = Admin_price::get_all();
        return ['status' => 'ok', 'prices' => $all['prices'], 'discounts' => $all['discounts']];
    }

    public static function update_prices_and_discounts($main_data){
        $post = self::post();
        $all = Admin_price::update_all($post);
        return ['status' => 'ok'];
    }
   
}