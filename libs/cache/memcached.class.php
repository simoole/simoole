<?php

namespace Root\Cache;

Class Memcached {
	
	Private $config = null;
    Private $instance = null;
	Public $prefix = null;
	
	Public function __construct(array $config = null)
	{
		if(is_array($config))$this->config = $config;
		if(empty($this->config)){
		    trigger_error('memcached配置出错！');
		    return;
        }
        $this->instance = new \Memcached('ssf');
        $this->instance->addServer($this->config['host'], $this->config['port']);
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
            if($this->instance->set($this->prefix . $key, $value, $timeout)){
                //记录索引
                $index = $this->instance->get('ssf_index');
                if(empty($index))$index = '|*|';
                $index .= $this->prefix . $key . '|*|';
                $this->instance->set('ssf_index', $index);
                return true;
            }
		}
		return false;
	}
	
	/**
	 * 读取缓存
	 * @param string $key
	 */
	Public function get(string $key)
	{
        return $this->instance->get($this->prefix . $key);
	}
	
	/**
	 * 删除指定缓存
	 * @param string $key
	 */
	Public function rm(string $key)
	{
	    if($this->instance->delete($this->prefix . $key)){
            $index = $this->instance->get('ssf_index');
            if(!empty($index)){
                $index = str_replace('|*|'. $this->prefix . $key .'|*|', '|*|', $index);
                $this->instance->set('ssf_index', $index);
            }
            return true;
        }
		return false;
	}

	/**
	 * 清理所有缓存
	 * @param bool $is_all 是否全部清除,默认只清除过期的缓存
	 * @return bool
	 */
	Public function clear(bool $is_all = false)
	{
		if($is_all){
		    $keys = $this->instance->get('ssf_index');
		    if($keys){
                $keys = trim(trim(trim($keys, '|'), '*'), '|');
                $keys = explode('|*|', $keys);
                if(empty($keys))return true;
                $index = '|*|';
                foreach($keys as $key){
                    if(strpos($key, $this->prefix) === 0)
                        $this->instance->delete($key);
                    else
                        $index .= $key . '|*|';
                }
                $this->instance->set('ssf_index', $index);
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
	    $keys = $this->instance->get('ssf_index');
	    if(!$keys)return [];
        $keys = trim(trim(trim($keys, '|'), '*'), '|');
        $keys = explode('|*|', $keys);
        if(empty($keys))return [];
        foreach($keys as $key) {
            if (!$regular) {
                if(strpos($key, $this->prefix) === 0 && strpos($key, $keyword) !== false)
                    $data[$key] = $this->instance->get($key);
            } else {
                if(strpos($key, $this->prefix) === 0 && preg_match($keyword, $key))
                    $data[$key] = $this->instance->get($key);
            }
        }
        return $data;
	}
	
}