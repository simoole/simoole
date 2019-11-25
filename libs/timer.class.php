<?php
/**
 * 核心Timer类
 * User: Dean.Lee
 * Date: 16/12/20
 */

namespace Root;

class Timer
{
    Static Public $chan = null;
    /**
     * 创建子进程用于构建定时器
     */
    Static public function create()
    {
        $process = new \swoole_process('\Root\Timer::onCreate');
        \Root::$serv->addProcess($process);
    }

    /**
     * 子进程创建回调
     * @param $process
     */
    Static Public function onCreate(\swoole_process $process)
    {
        global $argv;
        $pid = getmypid();
        $num = count(glob(TMP_PATH . 'child_*.pid'));
        file_put_contents(TMP_PATH . 'child_'. $num .'.pid', $pid);
        $process->name("Child[{$num}] process in <". __ROOT__ ."{$argv[0]}>");
        \swoole_process::signal(SIGSEGV, function() use ($pid){
            T('__PROCESS')->set('pid_' . $pid, [
                'memory_usage' => memory_get_usage(true),
                'memory_used' => memory_get_usage()
            ]);
        });

        T('__PROCESS')->set('pid_' . $pid, [
            'id' => $num,
            'type' => 4,
            'pid' => $pid,
            'receive' => 0,
            'sendout' => 0,
            'memory_usage' => memory_get_usage(true),
            'memory_used' => memory_get_usage()
        ]);

        \Root::$serv->tick(1000, '\Root\Timer::callback');
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

                self::call($path, $opt['get']??[], $opt['post']??[]);
            }
        }

        //清理过期session
        if($time % C('SESSION.CLEANUP') == 0){
            //清理过期的session
            \Root\Session::cleanup();
            //清理过期内存表
            \Root\Table::clearAll();
        }
    }

    /**
     * 发送HTTP请求
     * @param string $path
     * @param array $getParams
     * @param array $postParams
     */
    Static Private function call(string $path, array $getParams = [], array $postParams = [])
    {
        $cli = new \Swoole\Coroutine\Http\Client(C('SERVER.ip'), C('SERVER.port'));
        $cli->set(['timeout' => 60]);
        $url = '/' . $path . C('HTTP.ext');
        if(!empty($getParams))$url .= '?' . http_build_query($getParams);
        $time = round(microtime(true) * 10000);
        $cli->post($url, $postParams);
        $code = '['. date('Y-m-d H:i:s') .'][请求状态:' . $cli->statusCode . '][RunTime: '. (round(microtime(true) * 10000) - $time)/10000 .'s]' . PHP_EOL;
        $code .= $cli->getBody() . PHP_EOL;
        L($code, str_replace('/', '_', $path), '_crontab');
        $cli->close();
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
}