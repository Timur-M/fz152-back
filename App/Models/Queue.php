<?php

namespace App\Models;

class Queue extends Base
{
    public static function add_job($post){
        self::connect();
        $stmt = static::$pdo->prepare("INSERT INTO check_queue (site_id,date_add,check_type) 
            VALUES (:site_id,NOW() at time zone 'UTC',:check_type) RETURNING id");
        $stmt->execute([
            'site_id' => $post['site_id'],
            'check_type' => $post['check_type'],
        ]);
        return true;
    }

    public static function get_jobs(){
        self::connect();
        $stmt = static::$pdo->prepare("UPDATE check_queue SET status = 1, date_get = NOW() at time zone 'UTC'
            WHERE id IN (
                SELECT id FROM check_queue WHERE status = 0 ORDER BY id LIMIT 5
            ) RETURNING id, site_id, check_type");
        $stmt->execute();
        $result = $stmt->fetchAll();
        return $result;
    }
}