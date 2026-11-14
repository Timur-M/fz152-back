<?php

namespace App\Controllers;

use App\Models\Queue;
use App\Models\Worker;

class Cron extends Base
{
    
    public static function work(){
        $jobs = Queue::get_jobs();
        $result = 'nothing'.PHP_EOL;
        if(!empty($jobs)){
            $result = Worker::do_jobs($jobs);
        }
        echo $result;
    }

}