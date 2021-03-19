<?php
/**
 * 公用子进程类
 */

namespace Core\Util;
use Core\Base\Proc;

class ChildProc extends Proc
{
    private $queueData = [];
    private $mtable = [];
    private $websocket_data = [];

    public function onStart()
    {
        //清理过期的内存表
        \Swoole\Timer::tick(1000, function(){
            $time = time();
            foreach ($this->mtable as $mkey => $exp){
                if($exp < $time){
                    list($name, $key) = explode('^_^', $mkey);
                    \Core\Table::$table[$name]->del($key);
                    unset($this->mtable[$mkey]);
                }
            }

            foreach($this->websocket_data as $key => $data){
                if($data['last_receive_time'] < $time - 30 * 60){
                    unset($this->websocket_data[$key]);
                }
            }
        });

        //注册清空内存表信号
        \Swoole\Process::signal(SIGUSR2, function($signo) {
            foreach(\Core\Table::$table as $name => $table){
                T($name)->clear();
            }
            //清空全局内存
            $sum = \Core\Conf::server('SWOOLE', 'worker_num') + count(\Core\Sub::$procs);
            for($worker_id = 0; $worker_id < $sum; $worker_id ++){
                if($worker_id == \Core\Root::$worker->id)continue;
                \Core\Sub::send([
                    'act' => 'delGlobals',
                    'data' => []
                ], $worker_id);
            }
        });
    }

    public function onMessage($data, int $worker_id)
    {
        switch ($data['type']){
            case MEMORY_QUEUE_CLEAR:
                $this->queueData[$data['name']] = [];
                break;
            case MEMORY_QUEUE_LIST:
                return $this->queueData[$data['name']] ?? [];
            case MEMORY_QUEUE_PUSH:
                if(!isset($this->queueData[$data['name']]))$this->queueData[$data['name']] = [];
                $this->queueData[$data['name']][] = $data['data'];
                break;
            case MEMORY_QUEUE_POP:
                if(!isset($this->queueData[$data['name']]))return '[NULL]';
                return array_shift($this->queueData[$data['name']]);
            case MEMORY_QUEUE_COUNT:
                if(!isset($this->queueData[$data['name']]))return '[NULL]';
                return count($this->queueData[$data['name']]);

            case MEMORY_TABLE_SET:
                $this->mtable[$data['name'] . '^_^' . $data['key']] = time() + C('MEMORY_TABLE.' . $data['name'] . '.__expire');
                break;

            case MEMORY_WEBSOCKET_GET:
                if(isset($this->websocket_data['fd_' . $data['fd']]))
                    return $this->websocket_data['fd_' . $data['fd']];
                else return null;
            case MEMORY_WEBSOCKET_SET:
                if(!isset($this->websocket_data['fd_' . $data['fd']]))$this->websocket_data['fd_' . $data['fd']] = $data['data'];
                else $this->websocket_data['fd_' . $data['fd']] = array_mer($this->websocket_data['fd_' . $data['fd']], $data['data']);
                return true;
            case MEMORY_WEBSOCKET_DEL:
                unset($this->websocket_data['fd_' . $data['fd']]);
                return true;
            case MEMORY_WEBSOCKET_HEART:
                $data = [];
                foreach($this->websocket_data as $row){
                    if($row['worker_id'] == $worker_id)$data[$row['fd']] = $row['last_receive_time'];
                }
                return $data;
        }
    }
}
