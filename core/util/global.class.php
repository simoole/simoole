<?php

namespace Core\Util;
use Core\Conf;
use Core\Sub;
use Core\Root;

class Globals
{
    private $key = '';
    private $worker_id = null;
    public function __construct(string $name, int $worker_id = null)
    {
        $this->key = 'key_' . $name;
        $this->worker_id = $worker_id;
    }

    public function __set(string $name, $value = null) : void
    {
        $this->set($name, $value);
    }

    public function __get(string $name)
    {
        return $this->get($name);
    }

    public function __isset(string $name): bool
    {
        if(!isset($GLOBALS['__customize'][$this->key][$name]))return false;
        else return true;
    }

    public function __unset(string $name): void
    {
        if($this->worker_id == Root::$worker->id){
            unset($GLOBALS['__customize'][$this->key][$name]);
        }else{
            Sub::send([
                'act' => 'delGlobals',
                'data' => [$this->key, $name]
            ], $this->worker_id);
        }
    }

    /**
     * 获取全局数据
     * @param string|null $key 要获取的数据变量名
     * @return bool|mixed|null
     */
    public function get(string $key = null)
    {
        if($this->worker_id == Root::$worker->id || $this->worker_id === null){
            if(!isset($GLOBALS['__customize'][$this->key]))return null;
            if(!isset($GLOBALS['__customize'][$this->key][$key]))return null;
            if($key === null)
                return $GLOBALS['__customize'][$this->key];
            else
                return $GLOBALS['__customize'][$this->key][$key];
        }else{
            if($key === null)$keys = [$this->key];
            else{
                $keys = explode('.', $key);
                array_unshift($keys, $this->key);
            }
            return Sub::send([
                'act' => 'getGlobals',
                'data' => $keys
            ], $this->worker_id, true);
        }
    }

    /**
     * 设置全局数据
     * @param string $name 变量名
     * @param null $value 变量数据内容
     */
    public function set(string $name, $value = null) : void
    {
        $GLOBALS['__customize'][$this->key][$name] = $value;
        $sum = Conf::server('SWOOLE', 'worker_num') + count(Sub::$procs);
        if($this->worker_id == null){
            for($worker_id = 0; $worker_id < $sum; $worker_id ++){
                if($worker_id == Root::$worker->id)continue;
                Sub::send([
                    'act' => 'setGlobals',
                    'data' => [
                        $this->key => [
                            $name => $value
                        ]
                    ]
                ], $worker_id);
            }
        }elseif($this->worker_id >= 0 && $this->worker_id < $sum && $this->worker_id != Root::$worker->id){
            Sub::send([
                'act' => 'setGlobals',
                'data' => [
                    $this->key => [
                        $name => $value
                    ]
                ]
            ], $this->worker_id);
        }
    }
}
