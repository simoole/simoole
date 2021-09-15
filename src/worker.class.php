<?php
/**
 * 工作进程类
 * User: Dean.Lee
 * Date: 16/10/14
 */
namespace Simoole;

Class Worker
{
    //Worker进程ID
    public $id = null;

    //Worker进程的操作系统进程ID
    public $pid = null;

    static public $callFunc = [];

    /**
     * 工作/任务进程启动回调
     * @param swoole_server $server
     * @param $worker_id
     */
    static public function onstart(\swoole_server $server, int $worker_id)
    {
        //实例化进程对象
        Root::$worker = new self();
        file_put_contents(TMP_PATH . "worker_{$worker_id}.pid", $server->worker_pid);
        swoole_set_process_name("[". APP_NAME ."] Worker[{$worker_id}] process in <". __ROOT__ .">");
        echo "WorkerID[{$worker_id}] PID[". $server->worker_pid ."] creation finish!" . PHP_EOL;
        //工作进程启动后执行
        $method = Conf::app('worker_start');
        if(!empty($method))$method();
    }

    /**
     * 工作/任务进程终止回调
     * @param \swoole_server $server
     * @param int $worker_id
     */
    static public function onstop(\swoole_server $server, int $worker_id){
    }

    /**
     * 接收通道消息回调
     * @param swoole_server $server
     * @param int $from_worker_id
     * @param string $message
     */
    static public function pipeMessage(\swoole_server $server, int $from_worker_id, string $message)
    {
        $data = json_decode($message, true);
        $worker_num = Conf::swoole('worker_num');
        if(isset($data['act'])) {
            $act = $data['act'];
            if(method_exists(Root::$worker, $data['act'])){
                if(empty($data['data'])) Root::$worker->$act();
                else Root::$worker->$act($data['data']);
            }
        }elseif(isset($data['data']) && isset($data['worker_id']) && $data['worker_id'] >= 0 && $data['worker_id'] < $worker_num + Sub::$count && isset($data['cid'])){
            if(isset($data['callback'])) {
                if($data['callback'] == 2 && \Swoole\Coroutine::exists($data['cid'])){
                    Sub::$contents[$data['cid']] = $data['data'];
                    \Swoole\Coroutine::resume($data['cid']);
                    unset(Sub::$contents[$data['cid']]);
                    return true;
                }elseif(isset($data['data']['act']) && method_exists(Root::$worker, $data['data']['act'])){
                    $act = $data['data']['act'];
                    $res = Root::$worker->$act($data['data']['data']??null);
                    if($data['callback'] == 1){
                        if($data['worker_id'] < $worker_num)
                            Root::$serv->sendMessage(json_encode([
                                'data' => $res,
                                'cid' => $data['cid'],
                                'worker_id' => $data['worker_id'],
                                'callback' => 2
                            ]), $data['worker_id']);
                        else
                            Sub::socketSend($data['worker_id'] - $worker_num, [
                                'data' => $res,
                                'cid' => $data['cid'],
                                'worker_id' => $data['worker_id'],
                                'callback' => 2
                            ]);
                    }
                    return true;
                }
            }
            L("工作进程[{$data['worker_id']}]发来数据：\n" . var_export($data['data'], true), 'pipe', 'common');
        }
    }

    Private function __construct()
    {
        $this->id = Root::$serv->worker_id;
        $this->pid = Root::$serv->worker_pid;
        //加载函数库
        Root::loadFunc(APP_PATH);
        //加载应用类库
        Root::loadAppClass();
        //初始化数据库连接池
        Base\Model::_initialize();
        //启动心跳维持
        if(Conf::websocket('is_enable'))Websocket::heartbeat();
    }

    public function __call($name, $arguments)
    {
        if(isset(self::$callFunc[$name])){
            $func = self::$callFunc[$name];
            call_user_func_array($func, $arguments);
        }else trigger_error('Worker实例中未储备匿名函数['. $name .']');
    }

    /**
     * 获取工作进程内存占用大小
     */
    private function status()
    {
        make('__public')->putSize(Root::$worker->id, [
            'pid' => Root::$serv->getWorkerPid(),
            'status' => Root::$serv->getWorkerStatus(),
            'real_usage' => memory_get_usage(true),
            'usage' => memory_get_usage()
        ]);
    }

    /**
     * 利用进程管道发送数据
     * @param string $act 方法名
     * @param array $data 带入参数
     * @param int $worker_id 目标工作进程ID，-1为全部进程
     * @return bool
     */
    public function send(string $act, $data, int $worker_id = -1)
    {
        if($worker_id == $this->id){
            $this->$act($data);
            return true;
        }
        $datas = json_encode([
            'act' => $act,
            'data' => $data
        ]);
        $sum = Root::$serv->setting['worker_num'];
        if($worker_id > -1 && $worker_id < $sum){
            return Root::$serv->sendMessage($datas, $worker_id);
        }elseif($worker_id >= $sum){
            return Sub::send($datas, $worker_id);
        }else{
            for($i = 0; $i < $sum; $i++){
                if($i == $this->id){
                    $this->$act($data);
                    break;
                }
                Root::$serv->sendMessage($datas, $i);
            }
        }
        return true;
    }

    /**
     * 获取全局数据
     * @param null $keys
     * @return array|mixed
     */
    public function getGlobals($keys = null)
    {
        $res = $GLOBALS;
        $_res = [];
        if(is_string($keys) && isset($res[$keys]))$_res = $res[$keys];
        elseif(is_array($keys)){
            foreach($keys as $key){
                if(isset($res[$key]))
                    $res = $res[$key];
                else break;
            }
            if($res != $GLOBALS)$_res = $res;
        }
        return $_res;
    }

    /**
     * 设置对应进程的全局数据
     * @param array $data
     */
    public function setGlobals(array $data)
    {
        foreach($data as $key => $_data){
            foreach($_data as $_key => $val){
                $GLOBALS['__customize'][$key][$_key] = $val;
            }
        }
    }

    /**
     * 删除全局数据
     * @param array $data
     */
    public function delGlobals(array $keys)
    {
        if(empty($keys))$GLOBALS['__customize'] = [];
        else {
            $data = &$GLOBALS['__customize'];
            foreach($keys as $key){
                if(isset($data[$key]))$data = &$data[$key];
                else return false;
            }
            unset($data);
        }
    }

}
