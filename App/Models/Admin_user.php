<?php

namespace App\Models;

class Admin_user extends Base
{
    public static function get_users(array $post = []){
        self::connect();

        $conditions = '';
        if(!empty($post['search'])){
            $conditions .= " AND email like '%".htmlspecialchars($post['search'])."%'  ";
        }
        $order = 'id';

        $stmt = static::$pdo->prepare("SELECT COUNT(*) as total FROM users WHERE 1=1 ".$conditions);
        $stmt->execute([]);
        $count = $stmt->fetch();

        $total = $count['total'];

        $per_page = 10;

        $pages = ceil($total/$per_page);
        $page = 1;
        if(!empty($post['page'])){
            $page = $post['page'];
        }
        $start = $page*$per_page-$per_page;
        $limit = ' LIMIT '.$per_page.' OFFSET '.$start;

        $stmt = static::$pdo->prepare("SELECT u.*,
        to_char((u.date_add + interval '3 hours'),'DD-MM-YYYY HH24:MI') as date_add
         FROM users u WHERE 1=1 ".$conditions."
         ORDER BY ".$order." ".$limit);
        $stmt->execute([]);
        $result = $stmt->fetchAll();
        return ['result' => $result, 'total_pages' => $pages];
    }    

}