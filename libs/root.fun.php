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
function array_mer(array $config, array $_config)
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
 * 生成不可逆散列(40位)
 * @param string $string 待加密的字符串
 * @param bool|true $isNumber 是否为纯数字
 */
function createKey(string $string, bool $isNumber = false, string $keyt = null)
{
    $keyt = $keyt ?? C('KEYT');
    $array = str_split(sha1($string));
    $arr = [];
    foreach($array as $char){
        $arr[] = strpos($keyt, $char);
    }
    $code = [];
    if($isNumber){
        foreach($arr as $v){
            $code[] = $v + 16;
        }
    }else{
        foreach($arr as $k => $v){
            $v = ($v << ($k % 3)) % 62;
            $code[] = $keyt[$v];
        }
        if(is_numeric($code[0]))$code[0] = ['A','B','C','D','E','a','b','c','d','e'][$code[0]];
    }
    return join('', $code);
}

/**
 * SESSION控制函数
 * @param string $key
 * @param string $val [NULL]-获取session值|[START]-开启session|[ID]-获取sessionid|[HAS]-判断KEY值是否存在|[CLEAR]-清空session
 * @return boolean|multitype:
 */
function session(string $key, $val = '[NULL]')
{
	switch ($key){
		case '[START]':
		    if(is_object(U('session'))){
		        trigger_error('session_start() 只能执行一次！', E_USER_WARNING);
		        return false;
            }
			$id = is_string($val) && $val !== '[NULL]' ? $val : null;
            U()->session = new \Root\Session($id);
			return false;
		case '[ID]':
			return U()->session->getId();
		case '[HAS]':
			if(empty($val) || $val === '[NULL]')return false;
			return \Root\Session::has($val);
		case '[CLEAR]':
            U('session')->save([]);
		default:
			if($val === '[NULL]'){
				if(substr($key, 0, 1) === '?')
					return U('session')->exist(substr($key, 1));
				else{
					$arr = explode('.', $key);
					if(!U('session')->exist($arr[0])) return false;
					$data = U('session')->get($arr[0]);
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
			} elseif ($val === null){
                U('session')->del($key);
            } else {
				if(strpos($key, '.') === false){
                    U('session')->set($key, $val);
					return true;
				} else {
					$arr = explode('.', $key);
                    if(!U('session')->exist($arr[0])) return false;
                    $datas = U('session')->getData();
					$data = &$datas[$arr[0]];
					unset($arr[0]);
					foreach($arr as $v){
						if(isset($data[$v])){
							$data = &$data[$v];
						}else{
							return false;
						}
					}
					$data = $val;
                    U('session')->save($datas);
					return true;
				}
			}
	}
}

/**
 * 读取配置
 * @param $key 配置路径
 * @return mixed
 */
function C(string $key, $val = null)
{
    if($val === null){
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
    }else{
        \Root::$conf[$key] = $val;
        return true;
    }
}

/**
 * 实例化没有应用模型的数据模型
 * @param string $tablename 数据表名
 * @param string $dbname 数据库名
 * @return Root\Model
 */
function M(string $tablename = null, string $dbname = null)
{
    if(empty($dbname)){
	    $dbname = 'DB_CONF';
	    if(C(U('dbname')))
            $dbname = U('dbname');
    }
    if(!C($dbname)){
        trigger_error("Database config \"{$dbname}\" is not exist!", E_ERROR);
        return false;
    }
    $_model = new \Root\Model($dbname);
    if(!is_object($_model->db) || !is_object($_model->db->link)){
        trigger_error("Database config \"{$dbname}\" can't instantiate!", E_ERROR);
        return false;
    }
    if(!empty($tablename))$_model->table($tablename);
    return $_model;
}

/**
 * 应用模型实例化
 * @param string $tablename 数据表名
 * @param string $dbname 数据库名
 * @return Root\Model
 */
function D(string $tablename, string $dbname = null)
{
    $user = U();
    if(empty($dbname)){
        $dbname = 'DB_CONF';
        if(C(U('dbname')))
            $dbname = U('dbname');
    }
    if(!C($dbname)){
        trigger_error("Database config \"{$dbname}\" is not exist!", E_ERROR);
        return false;
    }
    $arr = explode(':', $tablename);

    if(is_object($user) && isset($user->mod_name))
        $module = $user->mod_name;
    else
        $module = C('HTTP.module');
    if(count($arr) == 2){
        $module = $arr[0];
        $tablename = $arr[1];
    }

    $class_name = ucfirst($module) . "\\Model\\" . ucfirst($tablename) . "Model";
    if(!isset(\Root::$map[$class_name]) || !class_exists($class_name)){
        trigger_error($class_name . ' is not exist!', E_ERROR);
        return false;
    }else{
        $_model = new $class_name($dbname);
        if(!is_object($_model->db) || !is_object($_model->db->link))return false;
    }

    if(method_exists($_model, '_init'))$_model->_init();
    return $_model;
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
	$_arr = explode('.', $name);
	if(!$user = U())return $default;
	if(in_array($arr[0], ['get','post','cookie','server','files','header','request','input'])){
		$act = $arr[0];
		$data = \Root::$user[getcid()]->$act;
		if(!empty($data) && is_string($data))return $data;
		if(!is_array($data) || empty($data)){
			return $default;
		}
		foreach($arr as $i => $ar){
			if($i > 0 && !empty($ar)){
				if(array_key_exists($ar, $data))
					$data = $data[$ar];
				elseif(array_key_exists($_arr[$i], $data))
                    $data = $data[$_arr[$i]];
				else{
					$data = $default;
					break;
				}
			}
		}
		return $data;
	}else{
		trigger_error($arr[0] . '不能用在I函数里!', E_USER_WARNING);
	}
}

/**
 * COOKIE控制函数
 * @param string $key cookie名
 * @param string $val cookie值-设置cookie值、null-删除cookie、'[NULL]'-获取cookie值（默认）
 * @param int $expire 到期时间 (默认会话级)
 * @return boolean|multitype:
 */
function cookie(string $key, string $val = '[NULL]', int $expire = null)
{
    $user = \Root::$user[getcid()];
    if($val === '[NULL]'){
        //查询
        $arr = explode('.', $key);
        if(empty($user->cookie) || empty($user->cookie[$arr[0]])) return false;
        $data = $user->cookie[$arr[0]];
        unset($arr[0]);
        foreach($arr as $v){
            if(isset($data[$v])){
                $data = $data[$v];
            }else{
                return false;
            }
        }
        return $data;
    } elseif ($val === null){
        //删除
        $user->response->cookie($key, '', -1);
    } else {
        //设置
        if(strpos($key, '.') === false){
            if($expire === null)
                $user->response->cookie($key, $val);
            else
                $user->response->cookie($key, $val, $expire);
            return true;
        } else {
            $arr = explode('.', $key);
            if(empty($user->cookie[$arr[0]])) return false;
            $datas = $user->cookie[$arr[0]];
            $data = &$datas[$arr[0]];
            unset($arr[0]);
            foreach($arr as $v){
                if(isset($data[$v])){
                    $data = &$data[$v];
                }else{
                    return false;
                }
            }
            $data = $val;
            if($expire === null)
                $user->response->cookie($arr[0], $datas);
            else
                $user->response->cookie($arr[0], $datas, $expire);
            return true;
        }
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
            trigger_error($dir . ' 该目录没有可写权限！', E_USER_WARNING);
            return;
        }
        chmod($dir, 0777);
    }
    if($d === '')
        $filepath = $dir . $prefix . 'record.log';
    else
        $filepath = $dir . $prefix . date($d) . '.log';
    if(!is_file($filepath) && ($keep = C('LOG.keep')) > 0){
        $files = scandir($dir);
        $_files = [];
        foreach($files as $file){
            if(strpos($file, $prefix . '_') !== false){
                $_files[] = [
                    'name' => $file,
                    'time' => explode('_', substr($file, strlen($prefix) + 1))
                ];
            }
        }
        usort($_files, function($a, $b){
            foreach($a['time'] as $k => $v){
                if(!isset($b['time'][$k]))return -1;
                $r = (int)$b['time'][$k] - (int)$v;
                if($r == 0)continue;
                return $r;
            }
        });
        foreach($_files as $i => $row){
            if($i >= $keep - 1){
                @unlink($dir . $row['name']);
            }
        }
    }
    if(!is_string($msg))$msg = var_export($msg, true);
    if(getcid() < 0) return @file_put_contents($filepath, $msg . PHP_EOL, FILE_APPEND);
    return \Swoole\Coroutine\System::writeFile($filepath, $msg . PHP_EOL, FILE_APPEND);
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
 * 获取底层User实例或该实例的属性
 * @param $pname 属性名称，默认返回User实例
 * @param $value 属性赋值
 * @return bool|string|object;
 */
function U(string $pname = null, $value = '[NULL]'){
    $uid = \Swoole\Coroutine::getcid();
    if(!isset(\Root::$user[$uid]))return false;

    $user = \Root::$user[$uid];
    if($pname === null)return $user;
    if(!isset($user->$pname))return false;

    if($value === '[NULL]')return $user->$pname;
    $user->$pname = $value;
    return true;
}

/**
 * 异步任务投放
 * @param string $name 任务名称
 * @param string|array $data 要投递的数据
 * @param callable|null $callback 回调函数（返回值，任务ID）
 */
function task(string $name, $data, callable $callback = null)
{
    if($callback === null)
        return \Root\Task::get($name)->add($data);
    else
        return \Root\Task::get($name)->add($data, $callback);
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
    if(!is_array($array))return (string)$array;
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

/**
 * 加锁
 * @param string $name 锁名
 * @param int $type 锁类型 0-自旋锁 1-异步锁 2-协程锁
 * @param int $expire 有效期(秒) 默认30秒，最大60秒
 */
function lock(string $name, int $type = 0, int $expire = 0)
{
    if(!isset($GLOBALS['__queue']))$GLOBALS['__queue'] = [];
    $expire = $expire === 0 ? 30 : ($expire > 60 ? 60 : $expire);
    if($type == 2){
        if(!isset($GLOBALS['__queue'][$name])){
            $GLOBALS['__queue'][$name] = [];
            \Swoole\Timer::after($expire * 1000, function() use ($name){
                if(empty($GLOBALS['__queue'][$name]))unset($GLOBALS['__queue'][$name]);
            });
        }else{
            $cid = getcid();
            \Swoole\Timer::after($expire * 1000, function() use ($cid, $name){
                if(\Swoole\Coroutine::exists($cid))\Swoole\Coroutine::resume($cid);
                if(empty($GLOBALS['__queue'][$name]))unset($GLOBALS['__queue'][$name]);
            });
            $GLOBALS['__queue'][$name][] = $cid;
            \Swoole\Coroutine::yield();
            $GLOBALS['__queue'][$name] = array_diff($GLOBALS['__queue'][$name], [$cid]);
        }
    }else{
        $lock = T('__LOCK')->get($name);
        if($lock === false || $lock['timeout'] < time()){
            if(!!$lock)T('__LOCK')->del($name);
            T('__LOCK')->set($name, [
                'type' => $type?1:0,
                'timeout' => time() + ($expire ?: 30)
            ]);
            return true;
        }else{
            //自旋锁
            if($lock['type'] == 0){
                \Swoole\Coroutine::sleep(0.05);
                lock($name, $type, $expire);
                //异步锁
            }elseif($lock['type'] == 1){
                return false;
            }
            return false;
        }
    }
}

/**
 * 检查锁
 * @param $name 锁名
 * @return bool true|false 是否有锁 返回false
 */
function trylock(string $name)
{
    $lock = T('__LOCK')->get($name);
    if($lock === false || $lock['timeout'] < time()){
        if(!!$lock)T('__LOCK')->del($name);
        return true;
    }else{
        return false;
    }
}

/**
 * 解锁
 * @param string $name 锁名
 * @return bool true|false 是否有锁
 */
function unlock(string $name)
{
    if(T('__LOCK')->exist($name))T('__LOCK')->del($name);
    elseif(isset($GLOBALS['__queue'][$name])){
        //唤醒队列中的一个协程
        if($cid = array_shift($GLOBALS['__queue'][$name]))
            if(\Swoole\Coroutine::exists($cid))\Swoole\Coroutine::resume($cid);
        //当队列为空则完全解锁
        if(empty($GLOBALS['__queue'][$name]))unset($GLOBALS['__queue'][$name]);
    }
    return true;
}

/**
 * 实例化并获取redis连接
 * @return null|Redis
 */
function getRedis()
{
    static $instance = null;
    if($instance === null || !$instance->ping()){
        $instance = new \Redis();
        $instance->pconnect(C('REDIS.host'), C('REDIS.port'), 0);
        $instance->auth(C('REDIS.pass'));
    }
    return $instance;
}

/**
 * 获取协程ID
 * @return mixed
 */
function getcid()
{
    return \Swoole\Coroutine::getcid();
}
