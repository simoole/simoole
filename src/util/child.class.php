<?php
/**
 * 公用子进程类
 */

namespace Simoole\Util;
use Simoole\Base\Proc;

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
                    \Simoole\Table::$table[$name]->del($key);
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
            foreach(\Simoole\Table::$table as $name => $table){
                T($name)->clear();
            }
            //清空全局内存
            $sum = \Simoole\Conf::swoole('worker_num') + count(\Simoole\Sub::$procs);
            for($worker_id = 0; $worker_id < $sum; $worker_id ++){
                if($worker_id == \Simoole\Root::$worker->id)continue;
                \Simoole\Sub::send([
                    'act' => 'delGlobals',
                    'data' => []
                ], $worker_id);
            }
        });

        $this->consoleInit();
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
                $this->mtable[$data['name'] . '^_^' . $data['key']] = time() + \Simoole\Conf::mtable($data['name'], '__expire');
                break;

            case MEMORY_WEBSOCKET_GET:
                if(isset($this->websocket_data['fd_' . $data['fd']]))
                    return $this->websocket_data['fd_' . $data['fd']];
                else return null;
            case MEMORY_WEBSOCKET_SET:
                if(!isset($this->websocket_data['fd_' . $data['fd']]))$this->websocket_data['fd_' . $data['fd']] = $data['data'];
                else $this->websocket_data['fd_' . $data['fd']] = arrayMerge($this->websocket_data['fd_' . $data['fd']], $data['data']);
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

    /**
     * 控制台初始化
     */
    public function consoleInit()
    {
        $sock = new \Swoole\Coroutine\Socket(AF_UNIX, SOCK_STREAM, 0);
        if(!$sock->bind(TMP_PATH . 'console.sock'))return;
        if(!$sock->listen())return;
        while(true) {
            $client = $sock->accept();
            if ($client) {
                go(function() use ($client){
                    while ($client->checkLiveness()){
                        $data = $client->recv();
                        $data = json_decode($data, true);
                        if(json_last_error() === JSON_ERROR_NONE && method_exists($this, $data['act'])){
                            $act = $data['act'];
                            if(empty($data['data'])) $res = $this->$act();
                            else $res = $this->$act($data['data']);
                            if(isset($res)){
                                if(is_array($res))$res = json_encode($res);
                                $client->send((string)$res);
                            }
                        }
                    }
                    $client->close();
                });
            }
        }
    }

    /**
     * 获取所有工作进程的状态
     */
    private function getWorkerList()
    {
        $data = [];
        for($i=0; $i<\Simoole\Root::$serv->setting['worker_num']; $i++){
            $data[] = [
                'pid' => file_get_contents(TMP_PATH . 'worker_' . $i . '.pid'),
                'status' => \Simoole\Root::$serv->getWorkerStatus($i)
            ];
        }
        return $data;
    }

    /**
     * 获取所有子进程的状态
     */
    private function getProcessList()
    {
        $processes = array_merge([
            '__public' => [
                'worker_num' => 1, //子进程进程数量
                'class_name' => '\Simoole\Util\Child' //子进程实例类名
            ]
        ], \Simoole\Conf::process());
        $data = [];
        $num = 0;
        foreach($processes as $name => $conf){
            for($i=0; $i<$conf['worker_num']; $i++){
                $data[] = [
                    'name' => $name,
                    'path' => $conf['class_name'],
                    'pid' => file_get_contents(TMP_PATH . 'child_' . $num . '.pid')
                ];
                $num ++;
            }
        }
        return $data;
    }

    private function getTableList()
    {
        $tables = \Simoole\Conf::mtable();
        $data = [];
        foreach($tables as $name => $conf){
            $table = T($name);
            if(!$table)continue;
            $keys = array_diff(array_keys($conf), ['__total', '__expire']);
            $data[] = [
                'name' => $name,
                'count' => $table->count(),
                'usage' => $table->memorySize(),
                'columns' => $keys
            ];
        }
        return $data;
    }

    private function handleMemTable(array $args)
    {
        $tablename = $args['name'];
        $command = $args['command'];
        $params = $args['params'] ?: [];

        $table = T($tablename);
        if(!$table)return $tablename . ' non-existent';
        return $table->$command(...$params);
    }

//    private function handleWorker(array $args)
//    {
//        $worker_id = $args['worker_id'];
//        $command = $args['command'];
//        $params = $args['params'] ?: [];
//
//        $this->send();
//    }
}
