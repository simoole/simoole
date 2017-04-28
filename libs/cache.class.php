<?php

namespace Root;

Class Cache {
	
	Static Private $config = [];
	Static Private $instance = null;
	
	/**
	 * 配置缓存并获取实例
	 * @param array $config 缓存配置数组
	 */
	Static Private function getInstance()
	{
		if(empty(self::$config))
			self::$config = \Root::$conf['CACHE'];
		if(self::$instance === null){
			$classname = '\\Root\\Cache\\' . ucfirst(strtolower(self::$config['DRIVE']));
			self::$instance = new $classname(self::$config);
		}
		return self::$instance;
	}
	
	/**
	 * 设置缓存配置
	 * @param array $config 缓存配置数组
	 */
	Static Public function setConfig(array $config)
	{
		return self::$config = array_merge(self::$config, $config);
	}
	
	/**
	 * 设置缓存键值前缀
	 * @param string $prifix 前缀字串
	 */
	Static Public function setPrifix(string $prifix)
	{
		self::getInstance()->prifix = $prifix;
	}
	
	/**
	 * 读取缓存
	 * @param string $key 缓存名
	 */
	Static Public function get(string $key)
	{
		return self::getInstance()->get($key);
	}
	
	/**
	 * 设置缓存
	 * @param string $key 缓存名
	 * @param string $value 缓存值
	 * @param int $timeout 缓存超时时间(s)
	 */
	Static Public function set(string $key, string $value, int $timeout = null)
	{
		return self::getInstance()->set($key, $value, $timeout);
	}
	
	/**
	 * 删除指定缓存
	 * @param string $key 缓存名
	 */
	Static Public function rm(string $key)
	{
		return self::getInstance()->rm($key);
	}

	/**
	 * 清理所有缓存
	 * @param bool $is_all 是否全部清除,默认只清除过期的缓存
	 * @return mixed
	 */
	Static Public function clear(bool $is_all = false)
	{
		return self::getInstance()->clear($is_all);
	}
	
	/**
	 * 关键字查询键，返回对应的键值对数组
	 * @param string $keyword 查询用的关键字
	 * @param bool $regular 是否使用正则查询
	 */
	Static Public function find(string $keyword, bool $regular = false)
	{
		return self::getInstance()->find($keyword, $regular);
	}
	
}

