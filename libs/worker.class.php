<?php
/**
 * 工作进程类
 * User: Dean.Lee
 * Date: 16/10/14
 */
namespace Root;

Class Worker
{
    //Worker进程ID
    Public $id = null;

    //Worker进程的操作系统进程ID
    Public $pid = null;

    //临时表
    Public $tmpTables = [];

    /**
     * 工作/任务进程启动回调
     * @param swoole_server $server
     * @param $worker_id
     */
    Static Public function onstart(\swoole_server $server, int $worker_id)
    {
        global $argv;
        //实例化进程对象
        \Root::$worker = new self();
        if($server->taskworker) {
            @file_put_contents(TMP_PATH . "task_{$worker_id}.pid", $server->worker_pid);
            \swoole_set_process_name("Tasker[{$worker_id}] process in <". __ROOT__ ."{$argv[0]}>");
            echo "TaskID[{$worker_id}] PID[". $server->worker_pid ."] creation finish!" . PHP_EOL;
            //路由任务配置
            foreach(\Root::$tasks as $conf){
                if(in_array($worker_id - $server->setting['worker_num'], $conf['ids'])){
                    \Root\Task::$callback = $conf['task_fun'];
                }
            }
        } else {
            @file_put_contents(TMP_PATH . "worker_{$worker_id}.pid", $server->worker_pid);
            \swoole_set_process_name("Worker[{$worker_id}] process in <". __ROOT__ ."{$argv[0]}>");
            echo "WorkerID[{$worker_id}] PID[". $server->worker_pid ."] creation finish!" . PHP_EOL;
            //绑定信号处理
            \swoole_process::signal(SIGUSR1, '\Root\Worker::signal');
        }

        T('__PROCESS')->set($server->worker_pid, [
            'id' => $worker_id,
            'type' => $server->taskworker ? 3:2,
            'pid' => $server->worker_pid,
            'receive' => 0,
            'sendout' => 0,
            'memory_usage' => memory_get_usage(true),
            'memory_used' => memory_get_usage()
        ]);
        \Root::$worker->createTmpTables();

        //工作进程启动后执行
        $method = C('APP.worker_start');
        if(!empty($method))$method();
    }

    /**
     * 工作/任务进程终止回调
     * @param \swoole_server $server
     * @param int $worker_id
     */
    Static Public function onstop(\swoole_server $server, int $worker_id){

    }

    /**
     * 信号处理回调
     */
    Static Public function signal(){
        T('__PROCESS')->set(\Root::$serv->worker_pid, [
            'memory_usage' => memory_get_usage(true),
            'memory_used' => memory_get_usage()
        ]);
    }

    /**
     * 接收通道消息回调
     * @param swoole_server $server
     * @param int $from_worker_id
     * @param string $message
     */
    Static Public function pipeMessage(\swoole_server $server, int $from_worker_id, string $message)
    {
        $data = json_decode($message, true);
        if(isset($data['act']) && property_exists(\Root::$worker, $data['act'])){
            $act = $data['act'];
            \Root::$worker->$act($data['data']);
        }
    }

    Private function __construct()
    {
        $this->id = \Root::$serv->worker_id;
        $this->pid = \Root::$serv->worker_pid;
        //加载函数库
        \Root::loadUtil(APP_PATH);
        //加载应用类库
        \Root::loadClass(APP_PATH);
        //加载配置文件
        \Root::$conf = \Root::loadConf();
        //初始化数据库连接池
        \Root\Model::_initialize();
    }

    /**
     * 利用进程管道发送数据
     * @param string $act
     * @param array $data
     * @param int $worker_id
     * @return bool
     */
    Public function send(string $act, $data, int $worker_id = -1)
    {
        if($worker_id == $this->id){
            $this->$act($data);
            return true;
        }
        $datas = json_encode([
            'act' => $act,
            'data' => $data
        ]);
        $sum = \Root::$serv->setting['worker_num'] + \Root::$serv->setting['task_worker_num'];
        if($worker_id > -1 && $worker_id < $sum){
            return \Root::$serv->sendMessage($datas, $worker_id);
        }
        for($i = 0; $i < $sum; $i++){
            if($i == $this->id){
                $this->$act($data);
                break;
            }
            \Root::$serv->sendMessage($datas, $i);
        }
        return true;
    }

    /**
     * 生成所有临时表
     */
    Public function createTmpTables(){
        foreach(\Root::$map as $classname => $row){
            if(strpos($row['classname'], 'RelModel') > 0){
                $tablename = substr($row['classname'], 0, -5);
                \Root::$worker->tmpTables[$tablename]['model'] = new $classname;
            }
        }
    }

    /**
     * 更新临时表
     * @param $tablename
     */
    Public function updateTmpTables(string $tablename)
    {
        $this->tmpTables[$tablename]['model']->_update();
    }

}