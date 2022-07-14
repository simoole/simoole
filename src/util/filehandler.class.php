<?php

namespace Simoole\Util;
use Swoole\Coroutine\System;

class FileHandler
{
    private $fp = null;
    private $data = [];

    public function __construct(string $filename)
    {
        $path = TMP_PATH . $filename;
        if(is_file($path)){
            $data = System::readFile($path);
            $data = json_decode($data, true);
            if(json_last_error() === JSON_ERROR_NONE){
                $this->data = $data;
            }
        }
        $this->fp = fopen($path, 'wb');
        if(!is_writable($path)){
            throw new \Exception("The $path is not writable!");
        }
        flock($this->fp, LOCK_EX);
    }

    /**
     * 获取全部数据
     * @return array
     */
    public function getAll() : array
    {
        return $this->data;
    }

    /**
     * 获取数据
     * @param string $key
     * @return mixed
     */
    public function get(string $key) : mixed
    {
        return $this->data[$key];
    }

    /**
     * 设置数据
     * @param string $key
     * @param mixed $data
     * @return void
     */
    public function set(string $key, mixed $data) : void
    {
        $this->data[$key] = $data;
        System::fread($this->fp, json_encode($this->data));
    }

    /**
     * 删除数据
     * @param string $key
     * @return void
     */
    public function del(string $key) : void
    {
        if(isset($this->data[$key])){
            unset($this->data[$key]);
            System::fread($this->fp, json_encode($this->data));
        }
    }

    /**
     * 判断key是否存在
     * @param string $key
     * @return bool
     */
    public function exists(string $key) : bool
    {
        return isset($this->data[$key]);
    }

    /**
     * 获取key数组
     * @return array
     */
    public function keys() : array
    {
        return array_keys($this->data);
    }

    /**
     * 获取数组元素数量
     * @return int
     */
    public function len() : int
    {
        return count($this->data);
    }

    /**
     * 获取Hash数据
     * @param string $key
     * @param string $name
     * @return mixed
     */
    public function hGet(string $key, string $name) : mixed
    {
        return $this->data[$key][$name];
    }

    /**
     * 设置Hash数据
     * @param string $key
     * @param string $name
     * @param mixed $data
     * @return void
     */
    public function hSet(string $key, string $name, mixed $data) : void
    {
        if(!isset($this->data[$key]))$this->data[$key] = [];
        $this->data[$key][$name] = $data;
        System::fread($this->fp, json_encode($this->data));
    }

    /**
     * 删除Hash数据
     * @param string $key
     * @param string $name
     * @return void
     */
    public function hDel(string $key, string $name) : void
    {
        if(isset($this->data[$key]) && isset($this->data[$key][$name])){
            unset($this->data[$key][$name]);
            System::fread($this->fp, json_encode($this->data));
        }
    }

    /**
     * 判断hash中key是否存在
     * @param string $key
     * @param string $name
     * @return bool
     */
    public function hExists(string $key, string $name) : bool
    {
        return isset($this->data[$key]) && isset($this->data[$key][$name]);
    }

    /**
     * 获取hash中的key数组
     * @param string $key
     * @return array
     */
    public function hKeys(string $key) : array
    {
        if(!isset($this->data[$key]) || !is_array($this->data[$key]))return [];
        return array_keys($this->data[$key]);
    }

    /**
     * 获取hash中元素数量
     * @param string $key
     * @return int
     */
    public function hLen(string $key) : int
    {
        if(!is_array($this->data[$key]))return 0;
        return count($this->data[$key]);
    }

    /**
     * 获取hash全部数据
     * @param string $key
     * @return array
     */
    public function hGetAll(string $key) : array
    {
        if(!is_array($this->data[$key]))return [];
        return $this->data[$key];
    }

    public function __destruct()
    {
        flock($this->fp, LOCK_UN);
        fclose($this->fp);
    }
}