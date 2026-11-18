<?php

namespace App\Models;

class Result extends Base
{
    public static function add_result($post){
        self::connect();
        
        $stmt = static::$pdo->prepare("INSERT INTO check_results (date_time,user_id,site_id,check_type,
        doc_urls,recom_url_token,amount,recom_content) VALUES (NOW() AT TIME ZONE 'UTC',:user_id,:site_id,:check_type,
        :doc_urls,:recom_url_token,:amount,:recom_content) RETURNING id");
        $stmt->execute([
            'user_id' => $post['user_id'],
            'site_id' => $post['site_id'],
            'check_type' => $post['check_type'],
            'doc_urls' => '{}',
            'recom_url_token' => uniqid(),
            'amount' => $post['amount'],
            'recom_content' => json_encode($post['result'],JSON_UNESCAPED_UNICODE),
        ]);
        
        $result = $stmt->fetch();
        return $result;
    }

    public static function get_result($post){
        self::connect();
        if(!empty($post['with_site'])){
            $select = ", s.url ";
            $join = ' LEFT JOIN sites s ON s.id = cr.site_id ';
        }
        $stmt = static::$pdo->prepare("SELECT cr.recom_content, cr.check_type ".$select." 
        FROM check_results cr
        ".$join."
        WHERE cr.recom_url_token = :t");
        $stmt->execute([
            't' => $post['result_token'],
        ]);
        $result = $stmt->fetch();
        return $result;
    }

    public static function get_results($post){
        self::connect();
        $stmt = static::$pdo->prepare("SELECT *,
        to_char((date_time + interval '3 hours'),'DD-MM-YYYY HH24:MI') as date_time
         FROM check_results WHERE site_id = :id");
        $stmt->execute([
            'id' => $post['id'],
        ]);
        $result = $stmt->fetchAll();
        return $result;
    }

    public static function update_result($post){
        self::connect();
        $stmt = static::$pdo->prepare("UPDATE check_results SET recom_content=:result, status=:status WHERE id=:id");
        $stmt->execute([
            'id' => $post['id'],
            'result' => json_encode($post['result'],JSON_UNESCAPED_UNICODE),
            'status' =>$post['status'],
        ]);
        $result = $stmt->fetchAll();
        return $result;
    }

    public static function get_txt($post){
        self::connect();
        $stmt = static::$pdo->prepare("SELECT recom_content FROM check_results WHERE id=:id");
        $stmt->execute(['id' => $post['id']]);
        $result = $stmt->fetch();
        $arr = json_decode($result['recom_content'], true);
        $txt = '';
        foreach($arr['checks'] as $c){
            $txt .= $c['title']. "\r\n";
            foreach($c['items'] as $i){
                $txt .= '  ';
                $txt .= $i['ok'] ? 'v' : 'x';
                $txt .= ' - ';
                $txt .= $i['text']."\r\n";
            }
            if (!empty($c['advice'])) $txt .= $c['advice']."\r\n";
            if (!empty($c['summary'])) $txt .= $c['summary']."\r\n";
        }
        $url = 'result-'.$post['id'].'.txt';
        $filename = $_SERVER['DOCUMENT_ROOT'].'/txt-files/'.$url;
        file_put_contents($filename, $txt);
        return $url;
    }
}