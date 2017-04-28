<?php
/**
 * 核心Timer类
 * User: Dean.Lee
 * Date: 16/12/20
 */

namespace Root;

class Timer
{
    Static Public $process = null;
    Static Public $chan = null;
    /**
     * 创建子进程用于构建定时器
     */
    Static public function create()
    {
        self::$process = new \swoole_process('\Root\Timer::onCreate');
        self::$process->start();
    }

    /**
     * 子进程创建回调
     * @param $process
     */
    Static Public function onCreate(\swoole_process $process)
    {
        global $argv;
        //转为守护进程
        \swoole_process::daemon();
        $pid = getmypid();
        $num = count(glob(TMP_PATH . 'child_*.pid'));
        @file_put_contents(TMP_PATH . 'child_'. $num .'.pid', $pid);
        $process->name("Child[{$num}] process in <{$argv[0]}>");
        \swoole_process::signal(SIGUSR1, function() use ($pid){
            T('__PROCESS')->set($pid, [
                'memory_usage' => memory_get_usage(true),
                'memory_used' => memory_get_usage()
            ]);
        });

        \swoole_process::signal(SIGALRM, '\Root\Timer::callback');
        //每秒执行一次
        \swoole_process::alarm(1000 * 1000);
        T('__PROCESS')->set($pid, [
            'id' => $num,
            'type' => 4,
            'pid' => $pid,
            'receive' => 0,
            'sendout' => 0,
            'memory_usage' => memory_get_usage(true),
            'memory_used' => memory_get_usage()
        ]);
    }

    /**
     * 定时器回调
     */
    Static Public function callback()
    {
        Static $crontabs = null;
        if($crontabs === null)$crontabs = C('TIMER');
        $time = time();
        if(!empty($crontabs)){
            foreach($crontabs as $path => $opt){
                //判断是否到了时间点
                if(!self::getInterval($opt['interval']))continue;
                //判断是否失效
                if($opt['timeout'] && $opt['timeout'] <= $time)continue;
                //判断剩余次数
                if($opt['repeat'] == 0)continue;
                //次数递减
                if($opt['repeat'] > 0)$crontabs[$path]['repeat'] --;

                $cli = new \swoole_http_client(C('SERVER.ip'), C('SERVER.port'));
                $cli->setHeaders([
                    'Host' => "localhost",
                    "User-Agent" => 'Chrome/49.0.2587.3',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml',
                    'Accept-Encoding' => 'gzip',
                ]);
                if(!empty($opt['post']))$cli->setData(http_build_query($opt['post']));
                $url = '/' . $path . C('APPS.ext');
                if(!empty($opt['get']))$url .= '?' . http_build_query($opt['get']);
                $cli->execute($url, function ($cli) use ($path) {
                    $code = '['. date('Y-m-d H:i:s') .'][请求状态:' . $cli->statusCode . ']' . PHP_EOL;
                    $code .= $cli->body . PHP_EOL;
                    L($code, str_replace('/', '_', $path), '_crontab');
                });
            }
        }

        //清理过期session
        if($time % C('SESSION.CLEANUP') == 0){
            self::sessionCleanup();
        }

        //清理过期cache
        \Root\Cache::clear();
    }

    /**
     * 定时器时间解析
     * @param string $interval 需要解析的时间字符串
     * @return bool 是否到点
     */
    Static Private function getInterval(string $interval)
    {
        $interval_arr = explode(' ', $interval);
        $interval_arr = array_reverse($interval_arr);

        $nowArr = [(int)date('w'), (int)date('H'), (int)date('i'), (int)date('s')];
        $sArr = [7, 24, 60, 60];
        foreach($interval_arr as $i => $t){
            if($t == '*'){
                continue;
            }elseif(strpos($t, '*/') === 0){
                $t = (int)str_replace('*/', '', $t);
                if($nowArr[$i] % $t == 0)
                    continue;
                else
                    return false;
            }else{
                $nums = [];
                if(strpos($t, ',') > 0){
                    $arr = explode(',', $t);
                    foreach($arr as $n){
                        $nums[] = $n;
                    }
                }else{
                    $nums[] = $t;
                }
                foreach($nums as $num){
                    if(strpos($num, '-') > 0){
                        $arr = explode('-', $num);
                        if($arr[0] > $arr[1])$arr[1] += $sArr[$i];
                        for($n=$arr[0]; $n<=$arr[1]; $n++){
                            if($nowArr[$i] == $n % $sArr[$i])continue 3;
                        }
                    }else
                        if($nowArr[$i] == $num)continue 2;
                }
                return false;
            }
        }
        return true;
    }

    /**
     * 清理过期session
     */
    Static private function sessionCleanup()
    {
        $table = T('__SESSION');
        $keys = [];
        $table->each(function(string $key, array $row){
            if($row['timeout'] < time()){
                $keys[] = $key;
            }
        });
        foreach($keys as $key){
            $table->del($key);
        }
    }
}