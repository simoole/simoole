<?php

namespace Root\Cache;

Class Filetmp {
	
	Private $config = null;
	Public $prefix = null;
	
	Public function __construct(array $config)
	{
		$this->config = $config;
		if(!is_writable($this->config['path'])){
			trigger_error($this->config['path'] . ' 该文件没有可写权限！', E_USER_ERROR);
			return false;
		}
		$this->prefix = $this->config['PREFIX'];
	}
	
	/**
	 * 设置缓存
	 * @param string $key
	 * @param string $value
	 */
	Public function set(string $key, $value, int $timeout = null)
	{
		if($timeout === null)$timeout = $this->config['TIMEOUT'];
		if($timeout > 7 * 24 * 60 * 60){
            trigger_error('缓存的超时时间不能超过7天！', E_USER_WARNING);
		}else{
			$data = json_encode(array(
				'timeout' => time() + $timeout,
				'value' => $value
			));
			if(!$fp = @fopen($this->config['path'] . $this->prefix . $key, 'w')){
                trigger_error($this->config['path'] . ' 该目录没有可写权限！', E_USER_ERROR);
			}else{
				fwrite($fp,$data,strlen($data));
				fclose($fp);
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
		$filepath = $this->config['path'] . $this->prefix . $key;
		if(!is_file($filepath))return false;
		$str = file_get_contents($filepath);
		$arr = json_decode($str, true);
		if(json_last_error() != JSON_ERROR_NONE || $arr['timeout'] < time()){
			unlink($this->config['path'] . $this->prefix . $key);
			return false;
		}
		return $arr['value'];
	}
	
	/**
	 * 删除指定缓存
	 * @param string $key
	 */
	Public function rm(string $key)
	{
		return unlink($this->config['path'] . $this->prefix . $key);
	}

	/**
	 * 清理所有缓存
	 * @param bool $is_all 是否全部清除,默认只清除过期的缓存
	 * @return bool
	 */
	Public function clear(bool $is_all = false)
	{
		if ($dh = opendir($this->config['path'])){
			while ($file = readdir($dh)){
				if(!is_dir($this->config['path'] . $file) && strpos($file, $this->prefix) !== false){
					if($is_all)unlink($this->config['path'] . $file);
					else {
						$str = file_get_contents($this->config['path'] . $file);
						$arr = json_decode($str, true);
						if(json_last_error() != JSON_ERROR_NONE || $arr['timeout'] < time()){
							unlink($this->config['path'] . $file);
							return false;
						}
					}
				}
			}
			return true;
		}
		return false;
	}
	
	/**
	 * 关键字查询键，返回对应的键值对数组
	 * string $keyword 查询用的关键字
	 * bool $regular 是否使用正则查询
	 */
	Public function find(string $keyword, bool $regular = false)
	{
		if ($dh = opendir($this->config['path'])){
			$data = [];
			while ($file = readdir($dh)){
				$key = substr($file, strlen($this->config['path'] . $this->prefix));
				if((!$regular && strpos($key, $keyword) !== false) || ($regular && preg_match($keyword, $key))){
					if($content = $this->get($key)) $data[$key] = $content;
				}
			}
			return $data;
		}
		return false;
	}
	
}