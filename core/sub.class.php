<?php
/**
 * 配置子进程
 */

namespace Core;

Class Sub
{
    Static Private $conf = [];
    Static Public $count = 0;
    Static Public $procs = [];
    Static Public $contents = [];

    Static Public function create()
    {
        static $num = 0;
        self::$conf = array_merge([
            'child' => [
                'worker_num' => 1, //子进程进程数量
                'class_name' => '\Core\Util\Child' //子进程实例类名
            ]
        ], Conf::process());
        foreach (self::$conf as $name => $conf){
            for($n=0; $n<$conf['worker_num']; $n++){
                self::$count ++;
            }
        }
        foreach (self::$conf as $name => $conf){
            for($n=0; $n<$conf['worker_num']; $n++){
                $process = new \Swoole\Process(function(\Swoole\Process $process) use ($num, $conf, $name){
                    self::onStart($num, $process, $conf, $name);
                }, 0, 2, true);
                Root::$serv->addProcess($process);
                self::$procs[$num] = $process;
                $num ++;
            }
        }
    }

    Static Public function onStart($num, $process, $conf, $name)
    {
        global $argv;
        $pid = getmypid();
        file_put_contents(TMP_PATH . 'child_'. $num .'.pid', $pid);
        $process->name("Child[{$num}] process in <". __ROOT__ ."{$argv[0]}>");

        //加载函数库
        Root::loadFunc(APP_PATH);
        //加载应用类库
        Root::loadAppClass();
        //初始化数据库连接池
        Base\Model::_initialize();

        swoole_event_add($process->pipe, function($pipe) use ($process,$num){
            $data = $process->read();
            go(function() use ($data, $num){
                self::onMessage($data, $num);
            });
        });

        $class_name = trim($conf['class_name'], '\\') . 'Proc';
        if(!class_exists($class_name) || !method_exists($class_name, 'onStart')){
            trigger_error($class_name . ' 不存在，子进程无法正常工作。');
        }else{
            Root::$worker = new $class_name($process, $name, $num);
            if(Root::$worker->onStart() === false){
                trigger_error($class_name . '::onStart() 执行失败，子进程无法正常工作。');
            }
        }
    }

    /**
     * 接收进程通信数据
     * @param $data 通信数据
     * @param $num 本次的进程ID
     * @return bool|void
     */
    Static Public function onMessage($data, $worker_id)
    {
        $data = json_decode($data, true);
        if(json_last_error() !== JSON_ERROR_NONE || !isset($data['data']) || !isset($data['worker_id']) || $data['worker_id'] < 0 || $data['worker_id'] >= Conf::server('SWOOLE','worker_num') + self::$count || !isset($data['cid']))return;
        //是否是发送回调
        if(isset($data['callback']) && $data['callback'] == 2 && \Swoole\Coroutine::exists($data['cid'])){
            self::$contents[$data['cid']] = $data['data'];
            \Swoole\Coroutine::resume($data['cid']);
            unset(self::$contents[$data['cid']]);
        }else{
            ob_start();
            $content = Root::$worker->onMessage($data['data'], $data['worker_id']);
            $_content = ob_get_clean();
            if(empty($content) && !empty($_content))$content = $_content;
            $content = $content ?: '';
            if(isset($data['callback']) && $data['callback'] == 1){
                $worker_num = Conf::server('SWOOLE','worker_num');
                if($data['worker_id'] < $worker_num)
                    Root::$serv->sendMessage(json_encode([
                        'data' => $content,
                        'cid' => $data['cid'],
                        'worker_id' => $worker_num + $worker_id,
                        'callback' => 2
                    ]), $data['worker_id']);
                else
                    self::$procs[$data['worker_id'] - $worker_num]->write(json_encode([
                        'data' => $content,
                        'cid' => $data['cid'],
                        'worker_id' => $worker_num + $worker_id,
                        'callback' => 2
                    ]));
            }
        }
        return true;
    }

    /**
     * 向目标进程发送数据
     * @param $data 发送的数据
     * @param $worker_id 工作进程ID或子进程名称，默认发给系统自带的子进程
     * @param bool $is_return 是否接收返回值(设置true后如果没有接收到返回值，会永远挂起当前协程)
     * @return bool|mixed
     */
    Static Public function send($data, $worker_id = null, bool $is_return = false)
    {
        static $arr = [];
        $cid = getcid();
        $worker_num = Conf::server('SWOOLE','worker_num');
        if(isset(Root::$worker->name))
            $_worker_id = $worker_num + Root::$worker->id;
        else
            $_worker_id = Root::$worker->id;
        if($worker_id === null)$worker_id = $worker_num;
        if(is_numeric($worker_id)) {
            if($worker_id < 0)return false;
            elseif($worker_id < $worker_num){
                Root::$serv->sendMessage(json_encode([
                    'data' => $data,
                    'cid' => $cid,
                    'worker_id' => $_worker_id,
                    'callback' => $is_return ? 1 : 0
                ]), $worker_id);
            }elseif($worker_id - $worker_num < self::$count){
                self::$procs[$worker_id - $worker_num]->write(json_encode([
                    'data' => $data,
                    'cid' => $cid,
                    'worker_id' => $_worker_id,
                    'callback' => $is_return ? 1 : 0
                ]));
            }else return false;
        }else{
            $conf = Conf::process((string)$worker_id);
            if(!$conf)return false;
            if(!isset($arr[(string)$worker_id])) $arr[(string)$worker_id] = 0;
            if($arr[(string)$worker_id] >= $conf['worker_num'])$arr[(string)$worker_id] = 0;
            $num = 0;
            foreach(Conf::process() as $name => $_conf){
                if($name == $worker_id)break;
                $num += $_conf['worker_num'];
            }
            $num += $arr[(string)$worker_id]++;
            self::$procs[$num]->write(json_encode([
                'data' => $data,
                'cid' => $cid,
                'worker_id' => $_worker_id,
                'callback' => $is_return ? 1 : 0
            ]));
        }
        if($is_return){
            //协程挂起等待返回值
            \Swoole\Coroutine::yield();
            return self::$contents[$cid];
        }
        return true;
    }

    /**
     * 解锁/唤醒协程
     * @param int $cid 被挂起的协程ID
     */
    Static Public function unlock(int $cid)
    {
        if(\Swoole\Coroutine::exists($cid))\Swoole\Coroutine::resume($cid);
    }
}
