<?php

namespace App\Models;
use App\Models\Full_check;
use App\Models\Result;
use App\Models\Balance;

class Worker extends Base
{
    public static function do_jobs($jobs){
        self::connect();
        $work_result = '';

        foreach($jobs as $j){
            $site_ids[] = $j['site_id'];
        }

        $stmt = static::$pdo->prepare("SELECT s.id,s.url,s.user_id,u.inn FROM sites s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.id IN (".implode(',',$site_ids).")");
        $stmt->execute();
        $sites = $stmt->fetchAll();

        foreach($sites as $s){
            $site[$s['id']] = $s;
        }

        $prices = Balance::get_prices();

        foreach($jobs as $j){
            $s = $site[$j['site_id']];
            if($j['check_type'] == '152'){
                $result = Full_check::fz_run_pd_full($s['url'],$s['inn']);
                $amount = $prices['152'];
            }
            if($j['check_type'] == 'sec'){
                $result = Full_check::fz_run_security_full($s['url']);
                $amount = $prices['sec'];
            }
            if(!empty($result)){
                Result::update_result([
                    'id' => $j['result_id'],
                    'result' => $result,
                    'status' => 1,
                ]);
                // ok. delete job
                $stmt = static::$pdo->prepare("DELETE FROM check_queue WHERE id=:id");
                $stmt->execute(['id' => $j['id']]);
                $work_result .= 'site-'.$s['id'].', check: '.$j['check_type'].' - ok'.PHP_EOL;
            } else {
                // error
                $stmt = static::$pdo->prepare("UPDATE check_queue SET status = 2 WHERE id=:id");
                $stmt->execute(['id' => $j['id']]);
                $work_result .= 'site-'.$s['id'].', check: '.$j['check_type'].' - error'.PHP_EOL;
            }
        }
        
        return $work_result;
    }

    

    
}