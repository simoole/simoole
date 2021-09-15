<?php
/**
 * 控制台
 * User: Dean.Lee
 * Date: 16/9/26
 */

namespace Simoole;

class Console {

    static Private $title = null;
    static Private $type = null;
    static Private $other_data = [];
    Const errortype = [
        E_ERROR              => 'Error',
        E_WARNING            => 'Warning',
        E_PARSE              => 'Parsing Error',
        E_NOTICE             => 'Notice',
        E_CORE_ERROR         => 'Core Error',
        E_CORE_WARNING       => 'Core Warning',
        E_COMPILE_ERROR      => 'Compile Error',
        E_COMPILE_WARNING    => 'Compile Warning',
        E_USER_ERROR         => 'User Error',
        E_USER_WARNING       => 'User Warning',
        E_USER_NOTICE        => 'User Notice',
        E_STRICT             => 'Runtime Notice',
        E_RECOVERABLE_ERROR  => 'Catchable Fatal Error'
    ];

    /**
     * 封装错误和异常处理
     */
    static public function load()
    {
        //error_reporting(0);
        //封装错误处理
        set_error_handler("Simoole\\Console::error");
        //封装异常处理
        set_exception_handler("Simoole\\Console::exception");
    }

    static public function error($errno, $errmsg, $filename, $linenum)
    {
        if(!in_array($errno, Conf::log('errortype')))return;
        $data = [
            'datetime' => date("Y-m-d H:i:s"),
            'errornum' => $errno,
            'errortype' => self::errortype[$errno],
            'errormsg' => $errmsg,
            'scriptname' => $filename,
            'linenum' => $linenum
        ];
        self::$type = $errno;
        if(function_exists('debug_backtrace')){
            $backtrace = debug_backtrace();
            if(!empty($backtrace)){
                array_shift($backtrace);
                $data['errcontext'] = [];
                foreach($backtrace as $i=>$l){
                    if(!isset($l['file']) || !isset($l['line']))continue;
                    $data['errcontext'][] = [
                        'num' => $i,
                        'action' => (isset($l['class'])?$l['class']:'') . (isset($l['type'])?$l['type']:'') . (isset($l['function'])?$l['function']:''),
                        'file' => $l['file'],
                        'line' => $l['line']
                    ];
                }
            }
        }
        self::output($data);
    }

    static public function exception($exception)
    {
        if(!in_array($exception->getCode(), Conf::log('errortype')))return;
        $data = [
            'datetime' => date("Y-m-d H:i:s"),
            'errortype' => self::errortype[$exception->getCode()],
            'errormsg' => $exception->getMessage(),
            'scriptname' => $exception->getFile(),
            'linenum' => $exception->getLine()
        ];
        self::$type = $exception->getCode();
        if(function_exists('debug_backtrace')){
            $backtrace = debug_backtrace();
            array_shift($backtrace);
            $data['errcontext'] = [];
            foreach($backtrace as $i=>$l){
                $data['errcontext'][] = [
                    'num' => $i,
                    'action' => ($l['class']?:'') . ($l['type']?:'') . ($l['function']?:''),
                    'file' => $l['file'],
                    'line' => $l['line']
                ];
            }
        }
        self::output($data);
    }

    /**
     * 错误或异常输出
     */
    static public function output($data)
    {
        if(self::$title !== null){
            $data = array_merge(['title' => self::$title], $data);
            self::$title = null;
        }
        if(!empty(self::$other_data)){
            foreach(self::$other_data as $key => $val){
                if(is_string($key))$data[$key] = $val;
            }
        }

        $mod_name = U('route_group') ?: 'common';
        if(Conf::log('errorfile') == 'xml')
            L(array2xml($data) . "\n\n", 'error', $mod_name);
        else
            L(json_encode($data) . PHP_EOL, 'error', $mod_name);
    }

    private $sock = null;
    private $choice = null;

    public function connect()
    {
        $this->sock = new \Swoole\Coroutine\Socket(AF_UNIX, SOCK_STREAM, 0);
        $res = $this->sock->connect(TMP_PATH . 'console.sock');
        if(!$res){
            echo APP_NAME . ' is not started!' . PHP_EOL;
            return false;
        }
        return true;
    }

    public function handle(string $result, string &$comstr) : bool
    {
        $arr = explode(' ', $result);
        $params = [];
        if(count($arr) <= 1 && empty($this->choice)){
            echo 'Invalid command ['. $result .']' . PHP_EOL;
            return true;
        }elseif(empty($this->choice)){
            $command = array_shift($arr);
            $object = array_shift($arr);
            if(!empty($arr))$params = $arr;
        }elseif(count($arr) > 1){
            $command = array_shift($arr);
            $params = $arr;
        }else $command = $result;
        $command = strtolower($command);
        switch ($command){
            case 'show':
                if(in_array(strtolower($object), ['workers', 'process', 'mtables']))
                    $this->showCommand($object);
                else
                    echo 'Invalid object ['. $object .']' . PHP_EOL;
                break;
            case 'select':
                if(in_array(strtolower($object), ['worker', 'process', 'mtable'])){
                    $res = $this->selectCommand($object, $params);
                    if(!empty($res))$comstr = $res;
                    else echo "Select {$object} failed" . PHP_EOL;
                }else
                    echo 'Invalid object ['. $object .']' . PHP_EOL;
                break;
            default:
                if(!empty($this->choice)){
                    if($this->choice[0] == 'mtable'){
                        $comms = ['get', 'set', 'del', 'count', 'incr', 'decr', 'keys', 'memorysize'];
                        if(!in_array($command, $comms)){
                            echo 'Command Invalid! Expected: ' . join(', ', $comms) . PHP_EOL;
                            return true;
                        }
                        $res = $this->send('handleMemTable', [
                            'name' => $this->choice[1],
                            'command' => $command,
                            'params' => $params
                        ]);
                        if(is_array($res))$res = json_encode($res);
                        echo $res . PHP_EOL;
                        return true;
                    }
                }
                echo 'Invalid command ['. $command .']' . PHP_EOL;
        }
        return true;
    }

    /**
     * 显示命令
     * @param $object
     */
    private function showCommand(string $object) : bool
    {
        if($object == 'workers'){
            $res = $this->send('getWorkerList');
            foreach($res as $i => $row){
                echo " {$i}. Worker[{$i}] PID:{$row['pid']} STATUS:" . ['UNKNOWN', 'BUSY', 'IDLE', 'EXIT'][$row['status']] . PHP_EOL;
            }
        }
        if($object == 'process'){
            $res = $this->send('getProcessList');
            foreach($res as $i => $row){
                echo " {$i}. Process[{$i}] NAME:{$row['name']} PID:{$row['pid']} PATH:{$row['path']}" . PHP_EOL;
            }
        }
        if($object == 'mtables'){
            $res = $this->send('getTableList');
            foreach($res as $i => $row){
                echo " {$i}. MemTable[{$row['name']}] COUNT:{$row['count']} USAGE:{$row['usage']}byte COLUMNS:" . join(',', $row['columns']) . PHP_EOL;
            }
        }
        return true;
    }

    private function selectCommand(string $object, array $params = []) : ?string
    {
        if($object == 'worker'){
            $worker_count = Conf::swoole('worker_num');
            $num = $params[0] ?? 0;
            if($num >= 0 && $num < $worker_count){
                echo "Select worker[{$num}] success." . PHP_EOL;
                $this->choice = [$object, $num];
                return "worker[{$num}]";
            }
        }
        if($object == 'process'){
            $process_conf = array_merge([
                '__public' => [
                    'worker_num' => 1, //子进程进程数量
                    'class_name' => '\Simoole\Util\Child' //子进程实例类名
                ]
            ], Conf::process());
            $sum = array_sum(array_column($process_conf, 'worker_num'));
            $num = $params[0] ?? 0;
            if($num >= 0 && $num < $sum){
                echo "Select process[{$num}] success." . PHP_EOL;
                $this->choice = [$object, $num];
                return "process[{$num}]";
            }
        }
        if($object == 'mtable'){
            $name = $params[0] ?? '';
            $table_conf = Conf::mtable();
            if(isset($table_conf[$name])){
                echo "Select mtable[{$name}] success." . PHP_EOL;
                $this->choice = [$object, $name];
                return "mtable[{$name}]";
            }
        }
        return null;
    }

    private function send(string $act, $data = null)
    {
        $this->sock->send(json_encode([
            'act' => $act,
            'data' => $data
        ]));
        $res = $this->sock->recv();
        if(!isset($res))return 'timeout..';
        $data = json_decode($res, true);
        if(json_last_error() === JSON_ERROR_NONE){
            return $data;
        }
        return $res;
    }
}
