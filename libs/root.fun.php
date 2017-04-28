<?php
/**
 * 框架默认函数
 * User: Dean.Lee
 * Date: 16/9/12
 */

/**
 * 合并多维数组
 * @param array $config 多维数组一
 * @param array $_config 多维数组二
 * @return Array 合并后的多维数组
 */
function array_mer($config, $_config)
{
	foreach($_config as $key => $val){
		if(array_key_exists($key, $config) && is_array($val)){
			$config[$key] = array_mer($config[$key], $_config[$key]);
		}else
			$config[$key] = $val;
	}
	return $config;
};

/**
 * 合成带键名的数组
 * @param array $array 数组一
 * @param array $array 数组二 ...
 * @return array 合成后的数组
 */
function array_key_merge()
{
	$data = func_get_args();
	$array = [];
	foreach($data as $d){
		if(is_array($d)){
			foreach($d as $k => $v) {
				$array[$k] = $v;
			}
		}
	}
	return $array;
}

/**
 * 生成不可逆散列
 * return string 散列的字符串
 */
function createHash($inText, $saltHash = null, $mode='sha1')
{
	if($saltHash == null)$saltHash = strrev($inText);
	return str_replace(['=', '-', '+', '_'], '', base64_encode(hash_hmac($mode, $inText, $saltHash . '&', false)));
}

/**
 * SESSION控制函数
 * @param string $key
 * @param string $val
 * @return boolean|multitype:
 */
function session($key, $val = '[NULL]')
{
	switch ($key){
		case '[START]':
			$id = is_string($val) && $val!='[NULL]' ? $val : null;
			\Root::$user->sessionStart($id);
			return false;
		case '[ID]':
			$id = is_string($val) && $val!='[NULL]' ? $val : null;
			return \Root::$user->sessionId($id);
		case '[HAS]':
			if(empty($val) || $val === '[NULL]')return false;
			return \Root::$user->sessionHas($val);
		case '[CLEAR]':
			$_SESSION = [];
		default:
			if($val === '[NULL]'){
				if(substr($key, 0, 1) === '?')
					return array_key_exists(substr($key, 1), $_SESSION);
				else{
					$arr = explode('.', $key);
					if(!array_key_exists($arr[0], $_SESSION)) return false;
					$data = $_SESSION[$arr[0]];
					unset($arr[0]);
					foreach($arr as $v){
						if(isset($data[$v])){
							$data = $data[$v];
						}else{
							return false;
						}
					}
					return $data;
				}
			} elseif ($val === null) unset($_SESSION[$key]);
			else return $_SESSION[$key] = $val;
	}
}

/**
 * 读取配置
 * @param $key 配置路径
 * @return mixed
 */
function C($key)
{
	$arr = explode('.', $key);
	$data = \Root::$conf;
	foreach($arr as $v){
		if(isset($data[$v])){
			$data = $data[$v];
		}else{
			return false;
		}
	}
	return $data;
}

/**
 * 实例化没有应用模型的数据模型
 * @param string $tablename 数据表名
 * @param string $dbname 数据库名
 * @return Root\Model
 */
function M(string $tablename = null, string $dbname = null)
{
	static $model = [];

	$guid = ($dbname?:'db_') . ($tablename?:'tb_');
	if(!isset($model[$guid])){
		$model[$guid] = new Root\Model($dbname);
		if(!empty($tablename))$model[$guid]->table($tablename);
	}
	return $model[$guid];
}

/**
 * 应用模型实例化
 * @param string $tablename 数据表名
 * @param string $dbname 数据库名
 * @return Root\Model
 */
function D($tablename, $dbname = null)
{
	static $model = [];
	$guid = ($dbname?:'db_') . ($tablename?:'tb_');
	if(!isset($model[$guid])){
		if(empty($tablename)){
			$model[$guid] = new \Root\Model($dbname);
			return $model[$guid];
		}
		$arr = explode(':', $tablename);

		$module = \Root::$user->mod_name;
		if(count($arr) == 2){
			$module = $arr[0];
			$tablename = $arr[1];
		}

		$classpath = '\\' . ucfirst($module) . '\\Model\\' . ucfirst($tablename) . 'Model';
		if(class_exists($classpath)){
			$model[$guid] = new $classpath($dbname);
			return $model[$guid];
		}

		$model[$guid] = new \Root\Model($dbname);
		if(!empty($tablename))$model[$guid]->table($tablename);
	}
	return $model[$guid];
}

/**
 * 输入通用函数
 * @param $name
 * @param bool $default
 * @return mixed
 */
function I($name, $default = false)
{
	$arr = explode('.', strtolower($name));
	if(($user = \Root::$user) === null)return $default;
	if(in_array($arr[0], ['get','post','cookie','server','files','header','request','input'])){
		$act = $arr[0];
		$data = \Root::$user->$act;
		if(!is_array($data) || empty($data)){
			return $default;
		}
		foreach($arr as $i => $ar){
			if($i > 0 && !empty($ar)){
				if(array_key_exists($ar, $data))
					$data = $data[$ar];
				else{
					$data = $default;
					break;
				}
			}
		}
		return $data;
	}else{
		trigger_error($arr[0] . '不能用在I函数里!', E_USER_ERROR);
	}
}

/**
 * 记录日志
 * @param string $msg 要记录的日志信息
 * @param string $prefix 前缀
 */
function L($msg, $prefix = 'user', $dirname = null)
{
	switch (C('LOG.split')){
		case 'i': $d = '_Y_m_d_H_i';break;
		case 'h': $d = '_Y_m_d_H';break;
		case 'd': $d = '_Y_m_d';break;
		case 'm': $d = '_Y_m';break;
		case 'w': $d = '_Y_W';break;
		default:$d = '';
	}

	$dir = LOG_PATH;
	if(!empty($dirname)){
		$dir = $dir . $dirname . '/';
		if(!is_dir($dir) && !mkdir($dir, 0777, true)){
			trigger_error($dir . ' 该目录没有可写权限！', E_USER_ERROR);
			return;
		}
		chmod($dir, 0777);
	}
	if($d === '')
		$filepath = $dir . $prefix . 'record.log';
	else
		$filepath = $dir . $prefix . date($d) . '.log';
	if(!is_string($msg))$msg = var_export($msg, true);
	if(\Root::$worker && !\Root::$serv->taskworker){
		swoole_async_write($filepath, $msg . PHP_EOL);
	}else{
		@file_put_contents($filepath, $msg . PHP_EOL, FILE_APPEND);
	}
	chmod($filepath, 0777);
}

/**
 * 实例化内存表
 * @param $tablename 要实例化的表名
 * @return boolean|multitype
 */
function T(string $tablename){
	Static $tables = [];
	if(!isset($tables[$tablename])){
		$tables[$tablename] = new \Root\Table($tablename);
	}
	return $tables[$tablename];
}

/**
 * 缓存操作函数
 * @param string $key
 * @param string $val
 * @param string $_val 增量或减量
 * @return boolean|multitype
 */
function cache($key, $val = '[NULL]', $_val = null)
{
	if($val === '[NULL]')return \Root\Cache::get($key);
	elseif($val === '[INC]')return \Root\Cache::set($key, (int)\Root\Cache::get($key) + $_val?:1);
	elseif($val === '[DEC]')return \Root\Cache::set($key, (int)\Root\Cache::get($key) - $_val?:1);
	elseif($val === '[INSET]'){
		$arr = \Root\Cache::get($key);
		if(empty($arr))$arr = [];
		$arr[] = $_val;
		return \Root\Cache::set($key, $arr);
	}elseif($val === '[OUTSET]'){
		$arr = \Root\Cache::get($key);
		if(empty($arr) || !in_array($_val, $arr))return false;
		$_arr = [];
		foreach($arr as $v){
			if($v !== $_val)$_arr[] = $v;
		}
		return \Root\Cache::set($key, $_arr);
	}
	elseif ($val === null)return \Root\Cache::rm($key);
	elseif(is_array($key))return \Root\Cache::set($key);
	else return \Root\Cache::set($key, $val, $_val);
}

/**
 * 生成随机码
 * @param int $len 随机码长度
 * @param bool|true $isNumber 是否为纯数字
 */
function createCode(int $len, bool $isNumber = true)
{
	$char = '1234567890qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM';
	if($isNumber){
		return rand(pow(10, $len - 1), pow(10, $len)) - 1;
	}else{
		$code = '';
		for($i=0; $i<$len; $i++){
			$code .= $char[rand(0, 61)];
		}
		return $code;
	}
}

/**
 * 数组转XML
 * @param $array 要转换的数组
 * @return String 输出的XML字符串
 */
function array2xml($array, $level = 0)
{
	$xml = '';
	if($level == 0)$xml .= "<root>\n";
	foreach($array as $key => $arr){
		if(is_array($arr)){
			if(!is_string($key)){
				$xml .= str_repeat("\t", $level+1);
				if($level == 1)
					$xml .= "<item>\n";
				else
					$xml .= "<item_" . ($level-1) . ">\n";
				$xml .= array2xml($arr, $level+1);
				$xml .= str_repeat("\t", $level+1);
				if($level == 1)
					$xml .= "</item>\n";
				else
					$xml .= "</item_" . ($level-1) . ">\n";
			}else{
				$xml .= str_repeat("\t", $level+1) . "<{$key}>\n";
				$xml .= array2xml($arr, $level+1);
				$xml .= str_repeat("\t", $level+1) . "</{$key}>\n";
			}
		}else{
			$xml .= str_repeat("\t", $level+1) . "<{$key}>{$arr}</{$key}>\n";
		}
	}
	if($level == 0)$xml .= "</root>\n";
	return $xml;
}

/**
 * 将数组中的每个值转为字符串类型
 * @param array $array 要转的数组
 * @return Array
 */
function array_change_value($array)
{
	foreach($array as $key => $val){
		if(is_array($val))
			$array[$key] = array_change_value($val);
		elseif($val === null)
			$array[$key] = '';
		else
			$array[$key] = (string)$val;
	}
	return $array;
}

/**
 * 检测端口是否可用
 * @param $host
 * @param $port
 * @return bool
 */
function checkPort($host, $port)
{
	$socket = @stream_socket_server("tcp://$host:$port", $errno, $errstr);
	if (!$socket) {
		return false;
	}
	fclose($socket);
	unset($socket);
	return true;
}


