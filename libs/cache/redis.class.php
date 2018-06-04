<?php

namespace Root\Cache;

Class Redis {
	
	Private $config = null;
    Private $instance = null;
	Public $prefix = null;
	
	Public function __construct(array $config = null)
	{
		if(is_array($config))$this->config = $config;
		if(empty($this->config)){
		    trigger_error('redis配置出错！');
		    return;
        }
        $this->instance = new \Redis();
        $this->instance->pconnect($this->config['host'], $this->config['port'], 0);
        if(!empty($this->config['pass']))$this->instance->auth($this->config['pass']);
		$this->prefix = $this->config['PREFIX'];
	}
	
	/**
	 * 设置缓存
	 * @param string $key
	 * @param string $value
     * @param int $timeout
	 */
	Public function set(string $key, $value, int $timeout = null)
	{
		if($timeout === null)$timeout = $this->config['TIMEOUT'];
		if($timeout > 7 * 24 * 60 * 60){
			trigger_error('缓存的超时时间不能超过7天！', E_USER_WARNING);
		}else{
		    if(is_array($value))$value = json_encode($value);
            $this->instance->set($this->prefix . $key, $value);
            $this->instance->expire($this->prefix . $key, $timeout);
            return true;
		}
		return false;
	}
	
	/**
	 * 读取缓存
	 * @param string $key
	 */
	Public function get(string $key)
	{
	    $data = $this->instance->get($this->prefix . $key);
        if($data){
            $_data = json_decode($data, true);
            if(json_last_error() === JSON_ERROR_NONE)$data = $_data;
        };
        return $data;
	}
	
	/**
	 * 删除指定缓存
	 * @param string $key
	 */
	Public function rm(string $key)
	{
		return $this->instance->del($this->prefix . $key);
	}

	/**
	 * 清理所有缓存
	 * @param bool $is_all 是否全部清除,默认只清除过期的缓存
	 * @return bool
	 */
	Public function clear(bool $is_all = false)
	{
		if($is_all){
		    $keys = $this->instance->keys($this->prefix . '*');
		    if($keys){
                foreach($keys as $key){
                    $this->instance->del($key);
                }
            }
            return true;
        }
	}
	
	/**
	 * 关键字查询键，返回对应的键值对数组
	 * string $keyword 查询用的关键字
	 * bool $regular 是否使用正则查询
	 */
	Public function find(string $keyword, bool $regular = false)
	{
	    $data = [];
        if(!$regular){
            $keys = $this->instance->keys($this->prefix . '*' . $keyword . '*');
            foreach($keys as $key){
                $data[$key] = $this->instance->get($key);
            }
        }else{
            $keys = $this->instance->keys($this->prefix . '*');
            foreach($keys as $key){
                if(preg_match($keyword, $key))
                    $data[$key] = $this->instance->get($key);
            }
        }
        return $data;
	}
	
}