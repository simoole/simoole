<?php
/**
 * 核心子进程类
 * User: Dean.Lee
 * Date: 19/12/25
 */

namespace Simoole\Base;

class Proc
{
    public $id = 0;
    public $pid = 0;
    public $name = null;
    public $worker = null;

    public function __construct(\Swoole\Process $process, string $name, int $num)
    {
        $this->worker = $process;
        $this->name = $name;
        $this->id = $num;
        $this->pid = getmypid();
    }

    public function onStart()
    {
    }

    public function onMessage($data, int $worker_id)
    {
    }

    protected function setWorkerGlobals($data)
    {
        for($i=0; $i<\Simoole\Root::$serv->setting['worker_num']; $i++){
            \Simoole\Root::$serv->sendMessage(json_encode([
                'act' => 'setGlobals',
                'data' => $data
            ]), $i);
        }
    }

    /**
     * 向目标进程发送数据
     * @param $worker_id 工作进程ID或子进程名称，默认发给系统自带的子进程
     * @param $data 发送的数据
     * @param bool $is_return 是否接收返回值(设置true后如果没有接收到返回值，会永远挂起当前协程)
     * @return bool|mixed
     */
    protected function send($worker_id, $data, $is_return = false)
    {
        $res = \Simoole\Sub::send($data, $worker_id, $is_return);
        return $res;
    }
}
