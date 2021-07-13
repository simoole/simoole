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
        for($i=0; $i<\Swoole\Root::$serv->setting['worker_num']; $i++){
            \Swoole\Root::$serv->sendMessage(json_encode([
                'act' => 'setGlobals',
                'data' => $data
            ]), $i);
        }
    }
}
