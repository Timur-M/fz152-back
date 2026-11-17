<?php

namespace App\Controllers;

use App\Models\Site;
use App\Models\Express;
use App\Models\User;
use App\Models\Balance;
use App\Models\Queue;
use App\Models\Result;


class Api extends Base
{
    
    public static function main($version, $method){
        $user_id = self::check_jwt_get_user_id();
        if($user_id){
            echo json_encode(self::$method(['api_version'=>$version,'user_id'=>$user_id]));
        } else {
            echo json_encode(['status' => 'error','error_text' => 'bad_jwt']);
        }
    }

    public static function add_site($main_data){
        $post = self::post();
        $site_id = Site::add_site([
            'url' => $post['url'],
            'user_id' => $main_data['user_id'],
        ]);
        return ['status' => 'ok', 'site_id' => $site_id];
    }

    public static function get_sites($main_data){
        $post = self::post();
        $sites = Site::get_sites([
            'user_id' => $main_data['user_id'],
        ]);
        return ['status' => 'ok', 'sites' => $sites];
    }

    public static function get_site($main_data){
        $post = self::post();
        $site = Site::get_site(['id' => $post['id']]);
        return ['status' => 'ok', 'site' => $site];
    }

    public static function delete_site($main_data){
        $post = self::post();
        $site = Site::delete_site(['id' => $post['id']]);
        return ['status' => 'ok'];
    }

    public static function update_site($main_data){
        $post = self::post();
        $site = Site::update_site($post['site']);
        return ['status' => 'ok'];
    }

    public static function check_express(){
        $post = self::post();
        $result = Express::sg_run_pd152($post['url']);
        file_put_contents($_SERVER['DOCUMENT_ROOT'].'/test.log', json_encode($result,JSON_UNESCAPED_UNICODE));
        return ['status' => 'ok'];
    }

    public static function get_user($main_data){
        $user = User::get_user($main_data['user_id']);
        return ['status' => 'ok','user' => $user];
    }

    public static function add_order($main_data){
        $post = self::post();
        $post['user_id'] = $main_data['user_id'];
        $payment_url = Balance::add_order($post);
        return ['status' => 'ok','PaymentURL' => $payment_url];
    }

    public static function get_prices($main_data){
        $prices = Balance::get_prices();
        return ['status' => 'ok','prices' => $prices];
    }

    public static function get_discounts($main_data){
        $discounts = Balance::get_discounts();
        return ['status' => 'ok','discounts' => $discounts];
    }

    public static function add_to_check_queue($main_data){
        $post = self::post();
        $post['user_id'] = $main_data['user_id'];
        $result = Queue::add_job($post);
        return ['status' => 'ok'];
    }

    public static function get_result(){
        $post = self::post();
        $result = Result::get_result($post);
        echo json_encode(['status' => 'ok','result' => $result]);
    }

    public static function get_results($main_data){
        $post = self::post();
        $result = Result::get_results($post);
        return ['status' => 'ok','results' => $result];
    }

    public static function update_user($main_data){
        $post = self::post();
        $post = $post['user'];
        $post['user_id'] = $main_data['user_id'];
        $result = User::update_user($post);
        return ['status' => 'ok'];
    }

    public static function get_orders($main_data){
        $post = self::post();
        $post['user_id'] = $main_data['user_id'];
        $orders = Balance::get_orders($post);
        return ['status' => 'ok','orders' => $orders];
    }

    public static function calc_balance($main_data){
        $post = self::post();
        $post['user_id'] = $main_data['user_id'];
        $orders = Balance::calc_balance($main_data['user_id']);
        return ['status' => 'ok'];
    }
}