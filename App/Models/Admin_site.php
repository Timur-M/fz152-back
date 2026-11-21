<?php

namespace App\Models;

class Admin_site extends Base
{
    

    public static function get_sites(array $post = []){
        self::connect();
        $stmt = static::$pdo->prepare("SELECT s.*, u.email as user_email,
            to_char((s.date_add + interval '3 hours'),'DD-MM-YYYY HH24:MI') as date_add
            FROM sites s
            LEFT JOIN users u ON s.user_id = u.id
            ORDER BY id");
        $stmt->execute([]);
        
        foreach($stmt->fetchAll() as $s){
            $last_checks = json_decode($s['last_checks'],true);
            if ($last_checks) foreach($last_checks as $lc_k => $lc_v){
                $date = new \DateTimeImmutable($lc_v);
                $newDate = $date->modify('+3 hours');
                $last_checks[$lc_k] = $newDate->format('d-m-Y H:i:s');
            }
            $s['last_checks'] = $last_checks;
            $result[] = $s;
        }
        return $result;
    }

    
}