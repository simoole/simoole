<?php

namespace Simoole\Util;
use Simoole\Conf;

class Storage
{
    /**
     * 清理过期缓存
     */
    static public function cleanup()
    {
        $conf = Conf::storage();
        \Swoole\Timer::tick($conf['CLEANUP'] * 1000, function() use ($conf){
            $redis = getRedis();
            $time = time();
            $list_key = $conf['PREFIX'] . '_key';
            $count = $redis->llen($list_key);
            for($i = 0; $i < $count; $i ++){
                $_key = $redis->rPop($list_key);
                $key = $conf['PREFIX'] . $_key;
                $data = $redis->hGetAll($key);
                if(empty($data)){
                    //删除空仓库
                    $redis->del($key);
                }else{
                    foreach($data as $k => $v){
                        if(!isset($v['expire']) || $v['expire'] < $time){
                            //删除过期仓库数据
                            $redis->hDel($key, $k);
                        }
                    }
                    $redis->lPush($list_key, $_key);
                }
            }
        });
    }

    private $_key = null;
    private $redis = null;
    private $expire = 0;
    private $key_prefix = null;
    private $_data = [];

    public function __construct(string $key, ?int $expire = null)
    {
        $conf = Conf::storage();
        $this->_key = $conf['PREFIX'] . $key;
        $this->key_prefix = $conf['KEY_PREFIX'];
        $this->redis = getRedis();
        $this->expire = $expire ?? $conf['EXPIRE'];
        if($this->expire == 0)$this->expire = 3600 * 24;
    }

    /**
     * 出栈
     */
    private function _get(string $name) : mixed
    {
        $name = $this->key_prefix . $name;
        $res = $this->redis->hGet($this->_key, $name);
        if(!$res)return null;
        $json = json_decode($res, true);
        $time = time();
        if(json_last_error() !== JSON_ERROR_NONE || !isset($json['expire']) || $json['expire'] < $time){
            $this->redis->hDel($this->_key, $name);
            return null;
        }
        if($this->expire < $time)$json['expire'] = $this->expire + $time;
        $this->_data = $json;
        if($json['type'] == 'object'){
            return unserialize($json['data']);
        }
        return $json['data'];
    }

    /**
     * 入栈
     */
    private function _set(string $name, mixed $value) : void
    {
        if(!$this->redis->exists($this->_key))
            $this->redis->lPush(Conf::storage('PREFIX') . '_key', $this->_key);
        $type = null;
        if(is_string($value))$type = 'string';
        if(is_bool($value))$type = 'boolean';
        if(is_array($value))$type = 'array';
        if(is_object($value)){
            $type = 'object';
            $value = serialize($value);
        }
        if(is_integer($value))$type = 'integer';
        if(is_float($value))$type = 'float';
        if(!$type){
            throw new \Exception('Storage不支持该类型');
        }
        $name = $this->key_prefix . $name;
        $this->redis->hSet($this->_key, $name, json_encode([
            'type' => $type,
            'data' => $value,
            'expire' => $this->expire + time()
        ]));
    }

    /**
     * 读取属性
     * @param string $name 属性名
     * @return mixed|null|string
     */
    public function __get(string $name)
    {
        return $this->_get($name);
    }

    /**
     * 属性赋值
     * @param string $name 属性名
     * @param string|number|array $value 属性值
     * @return int
     */
    public function __set(string $name, mixed $value)
    {
        $this->_set($name, $value);
    }

    /**
     * 获取属性名数组
     * @param string $search_string 搜索字符串 null-不匹配
     * @return array
     */
    public function keys(string $search_string = null)
    {
        $datas = $this->redis->hKeys($this->_key);
        if(!$datas)return [];
        $keys = [];
        foreach($datas as $name){
            $key = substr($name, strlen($this->key_prefix));
            if($search_string === null || strpos($key, $search_string) !== false){
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /**
     * 层级设置数据
     * @param array ...$keys 层级属性名
     * @param $val 最后一个参数是被设置的数值
     * @return bool|int
     */
    public function set(...$keys)
    {
        if(count($keys) < 2)return false;
        $key = array_shift($keys);
        $val = array_pop($keys);
        if(empty($keys)){
            $this->_set($key, $val);
        }else{
            Lock::set("{$this->_key}_{$key}_lock", 2);
            if($this->exist($key)){
                $data = $this->_get($key);
            }else $data = [];
            $_data = &$data;
            foreach($keys as $_key){
                if(!isset($_data[$_key])){
                    $_data[$_key] = [];
                }
                $_data = &$_data[$_key];
            }
            $_data = $val;
            $this->_set($key, $data);
            Lock::unset("{$this->_key}_{$key}_lock");
        }
        return true;
    }

    /**
     * 层级获取数据
     * @param \string[] ...$keys 层级属性名
     * @return bool|mixed|string
     */
    public function get(string ...$keys)
    {
        if(count($keys) < 1)return null;
        $key = array_shift($keys);
        if($this->exist($key)){
            $data = $this->_get($key);
        }else return null;
        if(count($keys) < 1)return $data;
        $_data = &$data;
        foreach($keys as $_key){
            if(!isset($_data[$_key]))return null;
            $_data = &$_data[$_key];
        }
        return $_data;
    }

    /**
     * 判断key是否存在
     * @param string $name
     * @return bool
     */
    public function exist(string $name) : bool
    {
        if($this->redis->hExists($this->_key, $this->key_prefix . $name)){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 获取元素个数
     * @return int
     */
    public function count() : int
    {
        return $this->redis->hLen($this->_key) ?: 0;
    }

    /**
     * 批量设置属性
     * @param array $data 要设置的名值对
     * @return bool
     */
    public function mset(array $data)
    {
        foreach($data as $k => $v){
            $this->_set($k, $v);
        }
        return true;
    }

    /**
     * 批量获取属性数组
     * @return array
     */
    public function mget()
    {
        $data = $this->redis->hGetAll($this->_key) ?: [];
        $_data = [];
        foreach($data as $k => $v){
            $key = substr($k, strlen($this->key_prefix));
            $json = json_decode($v, true);
            if(json_last_error() !== JSON_ERROR_NONE || !isset($json['expire']) || $json['expire'] < $time){
                $this->redis->hDel($this->_key, $k);
                continue;
            }
            if($json['type'] == 'object') $_data[$key] = unserialize($json['data']);
            else $_data[$key] = $json['data'];
        }
        return $_data;
    }

    /**
     * 遍历元素
     * @param callable $func [key, value]
     */
    public function each(callable $func)
    {
        $data = $this->mget();
        foreach($data as $k => $v){
            $func($k, $v);
        }
    }

    /**
     * 移除属性或仓库
     * @param string|null $name 属性名（默认移除仓库）
     * @return int
     */
    public function remove(string $name = null)
    {
        if($name === null)return $this->redis->del($this->_key);
        else return $this->redis->hDel($this->_key, $this->key_prefix . $name);
    }
}
