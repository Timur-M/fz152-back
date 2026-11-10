<?php

namespace App\Models;

class Jwt extends Base
{
    public static function get(array $post): string{
        self::connect();
        $stmt = static::$pdo->prepare("SELECT refresh FROM jwt WHERE user_id=:user_id");
        $stmt->execute(['user_id' => $post['user_id']]);
        $result = $stmt->fetch();
        return $result ? $result['refresh'] : $result;
    }  

    public static function set(array $post): string{
        self::connect();
        $refresh = uniqid();
        $stmt = static::$pdo->prepare("INSERT INTO jwt (refresh,user_id,dt) 
            VALUES (:refresh,:user_id,NOW() AT TIME ZONE 'Europe/Moscow')");
        $stmt->execute(['refresh' => $refresh,'user_id' => $post['user_id']]);
        return $refresh;
    } 

    public static function del(array $post): void{
        self::connect();
        $stmt = static::$pdo->prepare("DELETE FROM jwt WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $post['user_id']]);
    }  

    public static function refresh(array $post){
        self::connect();
        $stmt = static::$pdo->prepare("SELECT id FROM jwt WHERE refresh=:refresh AND user_id=:user_id");
        $stmt->execute(['refresh' => $post['refresh'],'user_id' => $post['user_id']]);
        $result = $stmt->fetch();
        return $result;
    }

    public static function encrypt(array $data): string {
        $app_conf = self::conf();
        $key = $app_conf['jwt_key'];
        $cipher = "aes-256-cbc";
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext = openssl_encrypt(json_encode($data), $cipher, $key, 0, $iv);
        return base64_encode($iv . $ciphertext);
    }

    public static function decrypt(string $encryptedData): array {
        $app_conf = self::conf();
        $key = $app_conf['jwt_key'];
        $cipher = "aes-256-cbc";
        $ivlen = openssl_cipher_iv_length($cipher);
        $decoded = base64_decode($encryptedData);
        $iv = substr($decoded, 0, $ivlen);
        $ciphertext = substr($decoded, $ivlen);
        return json_decode(openssl_decrypt($ciphertext, $cipher, $key, 0, $iv),true);
    }

    public static function get_user(array $post): array {
        self::connect();
        $cond = '';
        $bind = [];

        if(isset($post['email'])){
            $cond .= ' AND email = :email ';
            $bind['email'] = $post['email'];
        }

        if(isset($post['reg_code'])){
            $cond .= ' AND reg_code = :reg_code ';
            $bind['reg_code'] = $post['reg_code'];
        }

        if($cond != ''){
            $stmt = static::$pdo->prepare("SELECT * FROM users WHERE 1=1 ".$cond);
            $stmt->execute($bind);
            $result = $stmt->fetch();
        } else {
            $result = false;
        }
        
        return $result;
    }

    public static function reg_user(array $post) {
        self::connect();
        $stmt = static::$pdo->prepare("INSERT INTO users (email,password,reg_code) VALUES (:email,:password,:reg_code)");
        $stmt->execute([
            'email' => $post['email'],
            'password' => password_hash($post['password'], PASSWORD_DEFAULT),
            'reg_code' => $post['reg_code'],
        ]);
        return true;
    }

    public static function rcv_user(array $post) {
        self::connect();
        $stmt = static::$pdo->prepare("UPDATE users SET reg_code = :reg_code, rec_password = :rec_password WHERE email = :email");
        $stmt->execute([
            'email' => $post['email'],
            'rec_password' => password_hash($post['password'], PASSWORD_DEFAULT),
            'reg_code' => $post['reg_code'],
        ]);
        return true;
    }

    public static function rcv_fin_user(array $post) {
        self::connect();
        $stmt = static::$pdo->prepare("UPDATE users SET reg_code = NULL, password = rec_password, rec_password = NULL WHERE id = :id");
        $stmt->execute([
            'id' => $post['id'],
        ]);
        return true;
    }

    public static function clr_user(array $post) {
        self::connect();
        $stmt = static::$pdo->prepare("UPDATE users SET reg_code = NULL, rec_password = NULL WHERE id = :id");
        $stmt->execute([
            'id' => $post['id'],
        ]);
        return true;
    }

}