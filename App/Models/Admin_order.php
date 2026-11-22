<?php

namespace App\Models;

class Admin_order extends Base
{
    public static function get_orders(array $post = []){
        self::connect();
        $conditions = '';
        $order = 'id';

        $stmt = static::$pdo->prepare("SELECT COUNT(*) as total FROM payment_orders WHERE 1=1 ".$conditions);
        $stmt->execute([]);
        $count = $stmt->fetch();

        $total = $count['total'];

        $per_page = 5;

        $pages = ceil($total/$per_page);
        $page = 1;
        if(!empty($post['page'])){
            $page = $post['page'];
        }
        $start = $page*$per_page-$per_page;
        $limit = ' LIMIT '.$per_page.' OFFSET '.$start;

        $stmt = static::$pdo->prepare("SELECT o.*,u.email as user_email,
        to_char((o.date_create + interval '3 hours'),'DD-MM-YYYY HH24:MI') as date_create,
        to_char((o.date_update + interval '3 hours'),'DD-MM-YYYY HH24:MI') as date_update
         FROM payment_orders o 
         LEFT JOIN users u on o.user_id = u.id
         WHERE 1=1
         ORDER BY ".$order." ".$limit);
        $stmt->execute([]);
        $result = $stmt->fetchAll();

        return ['result' => $result, 'total_pages' => $pages];
    }    

}