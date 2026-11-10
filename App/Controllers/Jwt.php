<?php

namespace App\Controllers;

use App\Models\Jwt as model_jwt;

class Jwt extends Base
{
    public static function check(){
        $app_conf = self::conf();
        $post = self::post();
        $jwt_arr = model_jwt::decrypt($post['jwt']);
        if($jwt_arr and is_array($jwt_arr)){
            if($jwt_arr['exp'] >= (time() - 5*60)){
                echo json_encode(['status' => 'ok']);
            } else {
                echo json_encode(['status' => 'expired']);
            }
        } else {
            echo json_encode(['status' => 'not_found']);
        }
    }

    public static function refresh(){
        $app_conf = self::conf();
        $post = self::post();
        $jwt_arr = model_jwt::decrypt($post['jwt']);
        $refresh = model_jwt::refresh(['refresh' => $post['refresh'], 'user_id' => $jwt_arr['user_id']]);
        if($refresh){
            $jwt = model_jwt::encrypt(['user_id' => $jwt_arr['user_id'],'exp' => time()]);
            echo json_encode(['jwt' => $jwt]);
        } else {
            echo json_encode(['status' => 'not_found']);
        }
    }

    public static function login(){
        $app_conf = self::conf();
        $post = self::post();
        $user = model_jwt::get_user($post);
        if($user){
            if(password_verify($post['password'], $user['password'])){
                $jwt = model_jwt::encrypt(['user_id' => $user['id'],'exp' => time()]);
                $refresh = model_jwt::get(['user_id' => $user['id']]);
                if(!$refresh){
                    if($refresh != $post['refresh']){
                        model_jwt::del(['user_id' => $user['id']]);
                    }
                    $refresh = model_jwt::set(['user_id' => $user['id']]);
                }
                echo json_encode([
                    'status' => 'ok',
                    'jwt' => $jwt,
                    'refresh' => $refresh,
                    ]);
            } else {
                echo json_encode(['status' => 'wrong']);
            }
        } else {
            echo json_encode(['status' => 'not_found']);
        }
    }

    public static function register(){
        $app_conf = self::conf();
        $post = self::post();
        $post['reg_code'] = uniqid();
        model_jwt::reg_user($post);
        self::log($post['reg_code'],'main');
        echo json_encode([
            'status' => 'ok',
        ]);
    }

    public static function recover(){
        $app_conf = self::conf();
        $post = self::post();
        $post['reg_code'] = uniqid();
        model_jwt::rcv_user($post);
        self::log($post['reg_code'],'main');
        echo json_encode([
            'status' => 'ok',
        ]);
    }

    public static function approve(){
        $app_conf = self::conf();
        $post = self::post();
        if(!empty($post['reg_code'])){
            $user = model_jwt::get_user(['reg_code' => $post['reg_code']]);
            if($user){
                if(!empty($user['rec_password'])){
                    model_jwt::rcv_fin_user(['id'=>$user['id']]);
                } else {
                    model_jwt::clr_user(['id'=>$user['id']]);
                }
                $jwt = model_jwt::encrypt(['user_id' => $user['id'],'exp' => time()]);
                $refresh = model_jwt::set(['user_id' => $user['id']]);
                $result = [
                    'status' => 'ok',
                    'jwt' => $jwt,
                    'refresh' => $refresh,
                ];    
            } else {
                $result = ['status' => 'error'];
            }
        } else {
            $result = ['status' => 'error'];
        }
        echo json_encode($result);
    }
    
}