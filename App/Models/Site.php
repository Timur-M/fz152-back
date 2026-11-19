<?php

namespace App\Models;

class Site extends Base
{
    public static function add_site(array $post){
        self::connect();
        $stmt = static::$pdo->prepare("INSERT INTO sites (user_id,url,date_add) 
            VALUES (:user_id,:url,NOW() at time zone 'UTC') RETURNING id");
        $stmt->execute([
            'user_id' => $post['user_id'],
            'url' => $post['url'],
        ]);
        $result = $stmt->fetch();
        return $result;
    }    

    public static function get_sites(array $post){
        self::connect();
        $stmt = static::$pdo->prepare("SELECT *,
            to_char((date_add + interval '3 hours'),'DD-MM-YYYY HH24:MI') as date_add
            FROM sites WHERE user_id = :user_id ORDER BY id");
        $stmt->execute([
            'user_id' => $post['user_id'],
        ]);
        $result = $stmt->fetchAll();
        return $result;
    }

    public static function get_site(array $post){
        self::connect();
        $stmt = static::$pdo->prepare("SELECT *,
            to_char((date_add + interval '3 hours'),'DD-MM-YYYY HH24:MI') as date_add 
            FROM sites WHERE id = :id");
        $stmt->execute([
            'id' => $post['id'],
        ]);
        $result = $stmt->fetch();
        return $result;
    }

    public static function delete_site(array $post){
        self::connect();
        $stmt = static::$pdo->prepare("DELETE FROM sites WHERE id = :id");
        $stmt->execute([
            'id' => $post['id'],
        ]);
        return true;
    }

    public static function update_site(array $post){
        self::connect();
        
        $stmt = static::$pdo->prepare("SELECT * FROM sites WHERE id = :id");
        $stmt->execute(['id' => $post['id']]);
        $site = $stmt->fetch();

        $bind = ['id' => $post['id']];
        $upd = [];
        if(!empty($post['check_sets'])){
            $upd[] = ' check_sets = :check_sets ';
            $bind['check_sets'] = json_encode($post['check_sets']);
        } 
        
        if(isset($post['is_check_law_change'])){
            $upd[] = ' is_check_law_change = :is_check_law_change ';
            $bind['is_check_law_change'] = (int)$post['is_check_law_change'];
        } 

        if(isset($post['worker_last_check'])){
            $last_checks = json_decode($site['last_checks'],true);
            $last_checks[$post['check_type']] = $post['worker_last_check'];
            $upd[] = ' last_checks = :last_checks ';
            $bind['last_checks'] = json_encode($last_checks);
        } 
        
        if(!empty($upd)){
            $upd = implode(',',$upd);
            $stmt = static::$pdo->prepare("UPDATE sites SET ".$upd." WHERE id = :id");
            $stmt->execute($bind);
        }
        return true;
    }
}