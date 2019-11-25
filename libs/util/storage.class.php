<?php

namespace Root\Util;

class Storage
{
    private $_key = null;
    private $redis = null;

    public function __construct(string $key)
    {
        $this->_key = 'storage_' . $key;
        $this->redis = getRedis();
    }

    private function _continue()
    {
        if(!$this->redis->exists($this->_key))
            $this->redis->hMset($this->_key, []);
        //每次使用保存期延期
        $this->redis->expire($this->_key, C('REDIS.expire'));
    }

    /**
     * 读取属性
     * @param string $name 属性名
     * @return mixed|null|string
     */
    public function __get(string $name)
    {
        $this->_continue();
        $name = 'key_' . $name;
        if($this->redis->hExists($this->_key, $name)){
            $value = $this->redis->hGet($this->_key, $name);
            $val = json_decode($value, true);
            if(json_last_error() !== JSON_ERROR_NONE)
                $val = $value;
            return $val;
        } else return null;
    }

    /**
     * 属性赋值
     * @param string $name 属性名
     * @param string|number|array $value 属性值
     * @return int
     */
    public function __set(string $name, $value)
    {
        $this->_continue();
        $name = 'key_' . $name;
        if(is_numeric($value))$value = $value . '';
        if(!is_string($value))$value = json_encode($value);
        return $this->redis->hSet($this->_key, $name, $value);
    }

    /**
     * 获取属性名数组
     * @return array
     */
    public function keys()
    {
        $this->_continue();
        $datas = $this->redis->hKeys('key_' . $this->_key);
        $data = [];
        foreach($datas as $name){
            $data[] = substr($name, 4);
        }
        return $data;
    }

    /**
     * 层级设置数据
     * @param array ...$keys 层级属性名
     * @param $val 最后一个参数是被设置的数值
     * @return bool|int
     */
    public function set(...$keys)
    {
        $this->_continue();
        if(count($keys) < 3){
            trigger_error('Storage 的 set 方法参数必须大于2个...', E_USER_NOTICE);
            return false;
        }
        $val = array_pop($keys);
        lock($this->_key . '_lock');
        $datas = $this->redis->hGet($this->_key, 'key_' . $keys[0]);
        if(!$datas)$datas = [];
        else {
            $datas = json_decode($datas, true);
            if(json_last_error() !== JSON_ERROR_NONE || !is_array($datas)){
                unlock($this->_key . '_lock');
                trigger_error('Storage 的 set 方法所处理的数据层级太低！', E_USER_NOTICE);
                return false;
            }
        }
        $data = &$datas;
        for($i=1; $i < count($keys); $i++){
            if(!isset($data[$keys[$i]])){
                $data[$keys[$i]] = [];
            }
            $data = &$data[$keys[$i]];
        }

        //保存值
        $data = $val;
        unlock($this->_key . '_lock');
        return $this->redis->hSet($this->_key, 'key_' . $keys[0], json_encode($datas));
    }

    /**
     * 层级获取数据
     * @param \string[] ...$keys 层级属性名
     * @return bool|mixed|string
     */
    public function get(string ...$keys)
    {
        $this->_continue();
        if(count($keys) < 2){
            trigger_error('Storage 的 get 方法参数必须大于1个...', E_USER_NOTICE);
            return false;
        }
        $data = $this->redis->hGet($this->_key, 'key_' . $keys[0]);
        $data = json_decode($data, true);
        if(json_last_error() !== JSON_ERROR_NONE || !is_array($data))return false;

        for($i=1; $i < count($keys); $i++){
            if(!isset($data[$keys[$i]])){
                return null;
            }
            $data = $data[$keys[$i]];
        }
        return $data;
    }

    /**
     * 批量设置属性
     * @param array $data 要设置的名值对
     * @return bool
     */
    public function mset(array $data)
    {
        $this->_continue();
        $_data = [];
        foreach($data as $k => $v){
            if(is_numeric($v))$v = $v . '';
            if(!is_string($v))$v = json_encode($v);
            $_data['key_' . $k] = $v;
        }
        return $this->redis->hMset($this->_key, $_data);
    }

    /**
     * 批量获取属性数组
     * @return array
     */
    public function mget()
    {
        $this->_continue();
        $data = $this->redis->hGetAll($this->_key) ?: [];
        $_data = [];
        foreach($data as $k => $v){
            $key = substr($k, 4);
            $_data[$key] = json_decode($v, true);
            if(json_last_error() !== JSON_ERROR_NONE)
                $_data[$key] = $v;
        }
        return $_data;
    }

    /**
     * 移除属性或仓库
     * @param string|null $name 属性名（默认移除仓库）
     * @return int
     */
    public function remove(string $name = null)
    {
        if($name === null)return $this->redis->del($this->_key);
        else return $this->redis->hDel($this->_key, 'key_' . $name);
    }
}