<?php
/**
 * 框架默认函数
 * User: Dean.Lee
 * Date: 16/9/12
 */

/**
 * 获取环境变量
 * @param string $key
 * @param null $default
 * @return array|bool|mixed|string|null
 */
function env(string $key, $default = null)
{
    $_key = strtoupper(APP_NAME) . '_' . $key;
    $val = getenv($_key);
    switch ($val){
        case '[TRUE]': return true;
        case '[FALSE]': return false;
    }
    if($val === false){
        $val = getenv($key);
    }
    if($val === false)return $default;
    return $val;
}

/**
 * 合并多维数组
 * @param array $config 多维数组一
 * @param array $_config 多维数组二
 * @return Array 合并后的多维数组
 */
function arrayMerge(array $config, array $_config)
{
	foreach($_config as $key => $val){
		if(array_key_exists($key, $config) && is_array($val)){
			$config[$key] = arrayMerge($config[$key], $_config[$key]);
		}else{
		    if($val === null && isset($config[$key]))continue;
            $config[$key] = $val;
        }
	}
	return $config;
};

/**
 * 生成不可逆散列(40位)
 * @param string $string 待加密的字符串
 * @param bool|true $isNumber 是否为纯数字
 * @param string $keyt 加密字典
 */
function createKey(string $string, bool $isNumber = false, string $keyt = null)
{
    $keyt = $keyt ?? \Simoole\Conf::app('keyt');
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
            U()->session = new \Simoole\Session($id);
			return false;
		case '[ID]':
			return U()->session->getId();
		case '[HAS]':
			if(empty($val) || $val === '[NULL]')return false;
			return \Simoole\Session::has($val);
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
 * 读取自定义配置
 * @param $key 配置路径
 * @return mixed
 */
function C(string $key, $val = null)
{
    $arr = explode('.', $key);
    if($val !== null){
        $arr[] = $val;
    }
    return call_user_func_array("\\Simoole\\Conf::get", $arr);
}

/**
 * 实例化没有应用模型的数据模型
 * @param string $tablename 数据表名
 * @param string $dbname 数据库名
 * @return Simoole\Base\Model
 */
function M(string $tablename = null, string $dbname = null)
{
    if(empty($dbname)){
	    $dbname = 'DEFAULT';
	    if(\Simoole\Conf::database(U('dbname')))
            $dbname = U('dbname');
    }
    if(!\Simoole\Conf::database($dbname)){
        trigger_error("Database config \"{$dbname}\" is not exist!", E_USER_ERROR);
        return false;
    }
    $_model = new \Simoole\Base\Model($dbname);
    if(!is_object($_model->db) || !is_object($_model->db->link)){
        trigger_error("Database config \"{$dbname}\" can't instantiate!", E_USER_ERROR);
        return false;
    }
    if(!empty($tablename))$_model->table($tablename);
    return $_model;
}

/**
 * 应用模型实例化
 * @param string $tablename 数据表名
 * @param string $dbname 数据库名
 * @return Simoole\Base\Model
 */
function D(string $tablename, string $dbname = null)
{
    if(empty($dbname)){
        $dbname = 'DEFAULT';
        if(\Simoole\Conf::database(U('dbname')))
            $dbname = U('dbname');
    }
    if(!\Simoole\Conf::database($dbname)){
        trigger_error("Database config \"{$dbname}\" is not exist!", E_USER_ERROR);
        return false;
    }

    $class_name = "\\App\\Model\\" . ucfirst($tablename) . "Model";
    if(!isset(\Simoole\Root::$map[$class_name]) || !class_exists($class_name)){
        trigger_error($class_name . ' is not exist!', E_USER_ERROR);
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
	if(in_array($arr[0], ['get','post','cookie','server','files','header','request','input','json'])){
		$act = $arr[0];
		$data = $user->$act;
        if($data === [] && $act != 'input')return $default;
        if($data === '' && $act == 'input')return $default;
		if(!empty($data) && is_string($data))return $data;
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
    $user = \Simoole\Root::$user[getcid()];
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
    switch (\Simoole\Conf::log('split')){
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
    if(!is_file($filepath) && ($keep = \Simoole\Conf::log('keep')) > 0){
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
    return @file_put_contents($filepath, $msg . PHP_EOL, FILE_APPEND);
}

/**
 * 实例化内存表
 * @param string $tablename 要实例化的表名
 * @param bool $trigger_error 是否抛出错误
 * @return boolean|multitype
 */
function T(string $tablename, bool $trigger_error = true){
	static $tables = [];
	if(!isset($tables[$tablename])){
        $table = new \Simoole\Table();
        if($table->getInstance($tablename))
            $tables[$tablename] = $table;
        else{
            if($trigger_error)
                trigger_error($tablename . '内存表不存在!', E_USER_WARNING);
            return false;
        }
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
    $uid = getcid();
    if(!isset(\Simoole\Root::$user[$uid]))return false;

    $user = \Simoole\Root::$user[$uid];
    if($pname === null)return $user;
    if(!isset($user->$pname))return false;

    if($value === '[NULL]')return $user->$pname;
    $user->$pname = $value;
    return true;
}

/**
 * 实例化类
 * @param string $name 要实例化的类（子进程名:类名）
 * @param array $params 实例化参数
 * @return \Simoole\Sub|mixed|null
 */
function make(string $name, ...$params)
{
    if(class_exists($name)){
        $instance = (new ReflectionClass($name))->newInstanceArgs($params);
    }else{
        if($conf = \Simoole\Sub::$conf[$name]){
            return new class($name, $conf['class_name']){

                private $process_name = null;
                private $class_name = null;

                public function __construct($name, $class_name)
                {
                    $this->process_name = $name;
                    $this->class_name = $class_name;
                }

                public function __call($name, $params = [])
                {
                    //判断该方法是否有返回值
                    $is_return = (new ReflectionClass($this->class_name . 'Proc'))->getMethod($name)->hasReturnType();
                    return \Simoole\Sub::send([
                        '__actname' => $name,
                        '__params' => $params
                    ], $this->process_name, $is_return);
                }
            };
        }else{
            trigger_error("[{$name}]子进程不存在");
            return null;
        }
    }
    return $instance;
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
		$code = $char[rand(10, 61)];
		for($i=0; $i<$len - 1; $i++){
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
 * 配置文件中套用其他配置的值，转换函数
 * @param array $config 初始配置
 * @param array $_config
 * @return array 替换后的配置
 */
function changeConfigValue(array $config, array $_config = [])
{
    if(empty($_config))$_config = $config;
    foreach ($config as $key => $conf){
        if(is_array($conf) && !empty($conf))$config[$key] = changeConfigValue($conf, $_config);
        elseif(is_string($conf) && preg_match('/^\{\{(.+)\}\}$/', $conf, $str)){
            $arr = explode('.', $str[1]);
            $val = $_config;
            foreach ($arr as $name){
                $val = $val[$name];
            }
            $config[$key] = $val;
        }
    }
    return $config;
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
 * 实例化并获取redis连接
 * @return null|Redis
 */
function getRedis(string $name = 'DEFAULT')
{
    static $instance = [];
    $key = $name . '_' . getcid();
    if(!isset($instance[$key]) || !$instance[$key]->ping()){
        $conf = \Simoole\Conf::redis($name);
        if(!$conf){
            trigger_error('没有找到指定的REDIS配置', E_USER_ERROR);
            return false;
        }
        if($conf['USE_COROUTINE']){
            $instance[$key] = new \Swoole\Coroutine\Redis();
        }else{
            $instance[$key] = new \Redis();
        }
        if(!$instance[$key]->connect($conf['HOST'], $conf['PORT'])){
            trigger_error('Redis连接失败', E_USER_ERROR);
            return false;
        }
        if(!empty($conf['AUTH'])){
            $instance[$key]->auth($conf['AUTH']);
        }
        if(!$instance[$key]->select(intval($conf['DB']))){
            trigger_error('Redis仓库切换失败', E_USER_ERROR);
            return false;
        }
        \Swoole\Coroutine::defer(function() use (&$instance, $key){
            $instance[$key]->close();
            unset($instance[$key]);
        });
    }
    return $instance[$key];
}

/**
 * 获取协程ID
 * @return mixed
 */
function getcid()
{
    return \Swoole\Coroutine::getcid();
}

/**
 * 获取队列实例
 * @param string $name 队列名称
 * @return mixed
 */
function Q(string $name)
{
    static $instances = [];
    if(!isset($instances[$name])){
        $instances[$name] = new \Simoole\Util\Queue($name);
    }
    return $instances[$name];
}

/**
 * 获取全局数据实例
 * @param string $name 数据KEY
 * @return \Simoole\Util\Globals
 */
function G(string $name, int $worker_id = null)
{
    static $instances = [];
    if(!isset($instances[$name])){
        $instances[$name] = new \Simoole\Util\Globals($name, $worker_id);
    }
    return $instances[$name];
}

/**
 * 获取毫秒时间戳
 * @return int 返回的时间戳
 */
function mtime()
{
    return round(microtime(true) * 1000);
}

/**
 * 将ascii码转为字符串
 * @param array $arr 要解码的ASCII数组
 * @return string
 */
function decodeASCII(array $arr): string
{
    $utf = '';
    foreach($arr as $val){
        $dec = $val;
        if ($dec < 128) {
            $utf .= chr($dec);
        } else if ($dec < 2048) {
            $utf .= chr(192 + (($dec - ($dec % 64)) / 64));
            $utf .= chr(128 + ($dec % 64));
        } else {
            $utf .= chr(224 + (($dec - ($dec % 4096)) / 4096));
            $utf .= chr(128 + ((($dec % 4096) - ($dec % 64)) / 64));
            $utf .= chr(128 + ($dec % 64));
        }
    }
    return $utf;
}

/**
 * 将字符串转换为ascii码的数组
 * @param string $c 要编码的字符串
 * @return array
 */
function encodeASCII(string $str): array
{
    $c = preg_split('/(?<!^)(?!$)/u', $str);
    $len = count($c);
    $a = 0;
    $arr = [];
    while ($a < $len) {
        $ud = 0;
        if(ord($c[$a]) >= 0 && ord($c[$a]) <= 127){
            $ud = ord($c[$a]);
            $a += 1;
        }elseif(ord($c[$a]) >= 192 && ord($c[$a]) <= 223){
            $ud = (ord($c[$a]) - 192) * 64 + (ord($c[$a + 1]) - 128);
            $a += 2;
        }elseif(ord($c[$a]) >= 224 && ord($c[$a]) <= 239){
            $ud = (ord($c[$a]) - 224) * 4096 + (ord($c[$a + 1]) - 128) * 64 + (ord($c[$a + 2]) - 128);
            $a += 3;
        }elseif(ord($c[$a]) >= 240 && ord($c[$a]) <= 247){
            $ud = (ord($c[$a]) - 240) * 262144 + (ord($c[$a + 1]) - 128) * 4096 + (ord($c[$a + 2]) - 128) * 64 + (ord($c[$a + 3]) - 128);
            $a += 4;
        }elseif(ord($c[$a]) >= 248 && ord($c[$a]) <= 251){
            $ud = (ord($c[$a]) - 248) * 16777216 + (ord($c[$a + 1]) - 128) * 262144 + (ord($c[$a + 2]) - 128) * 4096 + (ord($c[$a + 3]) - 128) * 64 + (ord($c[$a + 4]) - 128);
            $a += 5;
        }elseif(ord($c[$a]) >= 252 && ord($c[$a]) <= 253){
            $ud = (ord($c[$a]) - 252) * 1073741824 + (ord($c[$a + 1]) - 128) * 16777216 + (ord($c[$a + 2]) - 128) * 262144 + (ord($c[$a + 3]) - 128) * 4096 + (ord($c[$a + 4]) - 128) * 64 + (ord($c[$a + 5]) - 128);
            $a += 6;
        }elseif(ord($c[$a]) >= 254 && ord($c[$a]) <= 255){
            $ud = null;
        }
        $arr[] = $ud;
    }
    return $arr;
}

/**
 * 将数组转的键值转换成由num和text组成的多维数组
 * @param $array
 * @return array|string
 */
function array_key_value($array)
{
    if(!is_array($array))return (string)$array;
    $return_arr = [];
    foreach($array as $key => $val){
        if(is_array($val))
            $return_arr[] = array_change_value($val);
        elseif($val === null)
            $return_arr[] = ['num'=>'', 'text'=>''];
        else
            $return_arr[] = ['num'=>(string)$key, 'text'=>(string)$val];
    }
    return $return_arr;
}
