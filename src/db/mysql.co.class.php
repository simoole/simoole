<?php

namespace Simoole\Db;

class mysqlCO
{
	//用于保存数据库长连接
	static private $links = [];
    //用于保存当前连接
	public $link = null;
	//用于保存数据库对象
	private $dbname = null;
	//用于保存table
	private $table = null;
	private $_table = null;
	private $asWord = '';
	//用于保存where
	private $where = null;
	//用于保存group
	private $group = null;
	//用于保存 order
	private $order = null;
	//用于保存 limit
	private $limit = null;
	//用于保存需要查询的字段
	private $field = null;
	//用于保存join
	private $join = null;
	//用于保存SQL
	public $sql = null;
	public $params = [];
	//字段信息
	private $fields = [];
	//储存数据库配置
	private $config = null;
	//储存查询日志
    private $runlogs = '';
    private $runtime = 0;

	//初始化数据库连接
	static public function _initialize(array $config, string $name = null)
    {
        if(!is_string($name))$name = 'DEFAULT';
        self::$links[$name] = new \Swoole\Coroutine\Channel($config['POOL']);
        //每分钟清理一次5分钟都没有被使用的空闲连接
        \Swoole\Timer::tick(60 * 1000, function() use ($name){
            if(self::$links[$name]->isEmpty())return;
            $conns = [];
            while($conn = self::$links[$name]->pop()){
                if($conn->use_time > time() - 5 * 60)
                    $conns[] = $conn;
            }
            foreach($conns as $conn)self::$links[$name]->push($conn);
        });
        return true;
    }

    /**
     * 连接数据库
     * @param array $config 数据库配置
     * @param int $is_pool 是否启用长连接
     * @return bool|\Co\MySQL
     */
    static private function _connect(array $config)
    {
        $conn = new \Swoole\Coroutine\MySQL();
        $rs = $conn->connect([
            'host' => $config['HOST'],
            'port'    => $config['PORT'],
            'user' => $config['USER'],
            'password' => $config['PASS'],
            'database' => $config['NAME'],
            'timeout' => 600,
            'charset' => $config['CHARSET'],
            'strict_type' => false,
            'fetch_mode' => true
        ]);
        if(!$rs){
            trigger_error('数据库连接失败！原因:['. $conn->connect_errno .']'. $conn->connect_error, E_USER_WARNING);
            return false;
        }
        return $conn;
    }

	/**
	 * 数据库连接
	 */
	public function __construct(array $config, string $name)
	{
		//获取数据库配置
		$this->config = $config;
		if(!empty($this->config)){
            $this->dbname = $config['NAME'];
            $uid = '__db' . getcid();
            //判断是否有配置连接池
            if(isset($config['POOL']) && $config['POOL']){
                //连接池是否为空
                if(self::$links[$name]->isEmpty()){
                    //增加多一个连接到连接池中
                    if($conn = self::_connect($config)){
                        self::$links[$name]->push($conn);
                    }else{
                        trigger_error('数据库连接异常，请检查config文件夹下的database.ini.php文件配置！', E_USER_WARNING);
                        return;
                    }
                }
                //从连接池中取出一个连接
                $this->link = self::$links[$name]->pop(3);
                //判断该连接是否可用，不可用则重连
                if(!$this->link || !$this->link->connected){
                    $this->link = null;
                    $this->__construct($config, $name);
                }
                $this->link->use_time = time();
            }else{
                //判断连接是否还在，还在则复用
                if(isset(self::$links[$uid]) && isset(self::$links[$uid][$name])) {
                    $this->link = self::$links[$uid][$name];
                    //判断该连接是否可用，不可用则重连
                    if(!$this->link || !$this->link->connected){
                        $this->link = null;
                        self::$links[$uid][$name] = null;
                        $this->__construct($config, $name);
                    }
                }elseif($conn = self::_connect($config)){
                    //建立新的连接
                    $this->link = $conn;
                    self::$links[$uid][$name] = $conn;
                    if(!U()){
                        $this->runtime = round(microtime(true) * 10000);
                        $this->runlogs = '';
                    }
                    //连接回收
                    \Swoole\Coroutine::defer(function() use ($uid, $name){
                        unset(self::$links[$uid][$name]);
                        if(empty(self::$links[$uid]))unset(self::$links[$uid]);
                        $this->link = null;
                        if(isset($this->runlogs) && !empty($this->runlogs)){
                            $name = \Simoole\Root::$worker->name ?? 'worker';
                            L($this->runlogs, $name, 'common');
                            unset($this->runlogs);
                            unset($this->runtime);
                        }
                    });
                }else
                    trigger_error('数据库连接异常，请检查config文件夹下的database.ini.php文件配置！', E_USER_WARNING);
            }
		}else{
		    $this->link = null;
            trigger_error('您的数据库信息尚未配置, 请在config文件夹下的database.ini.php中配置好您的数据库信息！', E_USER_WARNING);
        }
	}

	/**
	 * table选择数据表
	 */
	public function table(string $table, string $asWord = '')
	{
		$this->table = $this->config['PREFIX'] . $table;
		$this->_table = $table;
		if(preg_match('/^[a-zA-Z]+\w*$/', $asWord))$this->asWord = $asWord;
        if(!$tableFields = $this->_tableAnalyse($this->table))return false;
        foreach($tableFields as $row){
            $this->fields[$row['Field']] = null;
        }
		return true;
	}

	private function _tableAnalyse(string $table)
    {
        $datatable = $this->dbname . '.' . $table;
        if($data = G('database_' . $this->dbname)->$table){
            return $data;
        }
        $rs = $this->query("SHOW COLUMNS FROM `{$table}`");
        if(!$rs){
            trigger_error('数据表['. $datatable .']不存在！', E_USER_WARNING);
            return false;
        }
        $data = [];
        while($row = $rs->fetch()) {
            $data[] = $row;
        }
        G('database_' . $this->dbname)->$table = $data;
        return $data;
    }

	/**
	 * 输出字段
	 */
	public function __get(string $name)
	{
		foreach($this->fields as $key => $val){
			if($key == $name)return $val;
		}
		return false;
	}

	/**
	 * 字段设置
	 */
	public function __set(string $name, $value = null)
	{
		foreach($this->fields as $key => &$val){
			if($key == $name)return $val = $value;
		}
		trigger_error("在数据表 {$this->dbname}.{$this->table} 中没有字段 {$name}", E_USER_WARNING);
	}

	/**
	 * where条件判断
	 */
	public function where($where, string $table = null)
	{
		//组装where
        if(empty($where))return;
		if(is_array($where)){
            $tablename = $table?:($this->asWord?:'`'.$this->table.'`');
            $linkStr = $where[array_key_last($where)];
            if(is_string($linkStr) && in_array(strtolower($linkStr), ['or','and'])){
                array_pop($where);
                $_arr = [];
                foreach($where as $key => $row){
                    if(is_string($key))$row = [$key => $row];
                    $_arr = array_merge($_arr, $this->_where($row, $tablename));
                }
                $linkStr = strtolower($linkStr);
                $arr[] = '(' . join(" {$linkStr} ", $_arr) . ')';
            }else{
                $arr = $this->_where($where, $tablename);
            }
			$this->where = empty($this->where) ? $arr : array_merge($this->where, $arr);
		}else{
			$this->where[] = "($where)";
		}
	}

    /**
     * where条件拼接
     * @param $where
     * @param $table
     * @return array 输出的标准条件字符串
     */
	private function _where(array $where, string $tablename)
    {
        $arr = [];
        foreach($where as $key => $val){
            if(strpos($key, '.') !== false){
                $ar = explode('.', $key);
                $tablename = $ar[0];
                $key = $ar[1];
            }
            if(is_array($val)){
                if(is_string($val[0]) && strtolower($val[0]) === 'exp')
                    $arr[] = "({$tablename}.`{$key}` {$val[1]})";
                elseif(is_string($val[0]) && in_array(strtolower($val[0]), ['in', 'not in'])){
                    if(is_array($val[1]))$val[1] = "'" . join("','", $val[1]) . "'";
                    $arr[] = "({$tablename}.`{$key}` {$val[0]} ({$val[1]}))";
                }elseif(is_string($val[0]) && in_array(strtolower($val[0]), ['between', 'not between']) && count($val) == 3)
                    $arr[] = "({$tablename}.`{$key}` {$val[0]} '{$val[1]}' and '{$val[2]}')";
                elseif(is_string($val[0]) && in_array(strtolower($val[0]), ['=', '!=', '>', '<', '>=', '<=', '<>', 'like', 'not', 'not like']))
                    $arr[] = "({$tablename}.`{$key}` {$val[0]} '{$val[1]}')";
                else{
                    $char = ' and ';
                    $lastVal = strtolower($val[array_key_last($val)]);
                    if(in_array($lastVal, ['and', 'or'])){
                        $char = " {$lastVal} ";
                        array_pop($val);
                    }
                    $_arr = [];
                    foreach($val as $v){
                        if(is_array($v)){
                            if(is_string($v[0]) && strtolower($v[0]) === 'exp')
                                $_arr[] = "({$tablename}.`{$key}` {$v[1]})";
                            elseif(is_string($v[0]) && in_array(strtolower($v[0]), ['in', 'not in'])) {
                                if (is_array($v[1])) $v[1] = "'" . join("','", $v[1]) . "'";
                                $_arr[] = "({$tablename}.`{$key}` {$v[0]} ({$v[1]}))";
                            }elseif(is_string($v[0]) && in_array(strtolower($v[0]), ['between', 'not between']) && count($v) == 3)
                                $_arr[] = "({$tablename}.`{$key}` {$v[0]} '{$v[1]}' and '{$v[2]}')";
                            elseif(is_string($v[0]) && in_array(strtolower($v[0]), ['=', '!=', '>', '<', '>=', '<=', '<>', 'like', 'not', 'not like']))
                                $_arr[] = "({$tablename}.`{$key}` {$v[0]} '{$v[1]}')";
                        }else{
                            $_arr[] = "({$tablename}.`{$key}` = ?)";
                            $this->params[] = (string)$v;
                        }
                    }
                    if(!empty($_arr)) $arr[] = "(". join($char, $_arr) .")";
                }
            }elseif($val === null)
                $arr[] = "({$tablename}.`{$key}` is null)";
            else{
                $arr[] = "({$tablename}.`{$key}` = ?)";
                $this->params[] = $val;
            }
        }
        return $arr;
    }

	/**
	 * order by组装
	 */
	public function order($order, string $asc = '', string $table = null)
	{
		$tablename = $table?:($this->asWord?:'`'.$this->table.'`');
		if(is_array($order)){
			foreach($order as $key => $val){
				if(empty($key)){
                    if(strpos($val, '(') === false)
                        $this->order[] = "{$tablename}.`{$val}` {$asc}";
                    else
                        $this->order[] = "{$val} {$asc}";
                }else{
                    if(strpos($key, '(') === false)
                        $this->order[] = "{$tablename}.`{$key}` {$val}";
                    else
                        $this->order[] = "{$key} {$val}";
                }
			}
		}else{
            if(strpos($order, '(') === false)
                $this->order[] = "{$tablename}.`{$order}` {$asc}";
            else
                $this->order[] = "{$order} {$asc}";
        }
	}

	/**
	 * field 字段组装
	 */
	public function field($field, string $table = null)
	{
        $tablename = $table?:($this->asWord?:'`'.$this->table.'`');
		if(empty($field) || $field === '*'){
            $this->field[] = "{$tablename}.*";
		    return;
        }
		if(!is_array($field))$field = explode(',', $field);

		foreach($field as $key => $val){
			if(is_string($key)){
				$fieldname = $key;
			}else{
				$fieldname = $val;
			}
			if(strpos($fieldname, '(') === false){
				$key = "{$tablename}.`{$fieldname}`";
			}else{
				$val = $val==$fieldname ? null : $val;
				$fieldname = str_replace('"', "'", trim($fieldname));
				if(!preg_match('/^(\w+)\((.*?)\)$/', $fieldname, $arr))continue;
				$fn = $arr[1];
				$params = explode(',', $arr[2]);
				foreach($params as &$param){
                    $param = trim($param);
                    if(preg_match('/^\w+$/', $param)){
                        $param = "{$tablename}.`{$param}`";
                    }elseif(preg_match('/^`\w+`$/', $param)){
                        $param = "{$tablename}.{$param}";
                    }
                }
				$key = "{$fn}(". join(',', $params) .")";
			}
			$this->field[$val] = $key;
		}
	}

	/**
	 * limit组装
	 */
	public function limit($limit, int $length = null)
	{
		if(!empty($limit) && empty($length)){
			$this->limit = 'limit ' . $limit;
		}elseif(is_numeric($limit) && is_numeric($length)){
			$this->limit = 'limit ' . $limit . ',' . $length;
		}
	}

	/**
	 * JOIN多表关联
	 * @param string $table
	 * @param array $condition array('主表字段'=>array('比较字符', '关联表字段'))
	 * @param string $type
	 */
	public function join(string $table, array $condition = [], string $type = 'inner')
	{
		$word = '';
		if(strpos($table, ' ') > 0)
			list($table, $word) = explode(' ', $table, 2);
		$table = preg_replace_callback('/(?!^)[A-Z]{1}/', function($result){
			return '_' . strtolower($result[0]);
		}, $table);
		$table = strtolower($table);
		$table = $this->config['PREFIX'] . $table;
		$word = trim(str_replace('as', '', $word));
		$tablename = $this->asWord ? : '`'.$this->table.'`';
		$_tablename = !empty($word) ? $word : '`'.$table.'`';
		$onWhere = [];
		foreach($condition as $key => $val){
			$a = $_tablename;
			$b = $tablename;
			$c = $key;
			$d = $val;
			$e = '=';
			if(strpos($key, '.') !== false){
				$arr = explode('.', $key);
				$a = $arr[0];
				$c = $arr[1];
			}
			if(is_array($val)){
				$e = $val[0];
				$d = $val[1];
			}
			if(preg_match('/^[a-zA-Z_]\w*\.[a-zA-Z_]\w*$/', $d)){
				$arr = explode('.', $d);
				$b = $arr[0];
				$d = $arr[1];
			}
			$onWhere[] = "({$a}.`{$c}` {$e} {$b}.`{$d}`)";
		}
        $type = strtolower($type);
		if(!in_array($type, ['left','right']))$type = 'inner';
		$this->join[] = "{$type} join `{$table}` {$word} on " . join(' and ', $onWhere);
	}

	/**
	 * GROUP子句组合
	 * @param string $field
	 * @param array $having array('字段' => array('比较字符', '值', '字段的作用函数'))
	 */
	public function group(string $field, array $having = [], string $table = null)
	{
		if(empty($field))return false;
		if(strpos($field, '.') > 0){
			$arr = explode('.', $field);
			$table = $arr[0];
			$field = $arr[1];
		}
		$tablename = $table?:($this->asWord?:'`'.$this->table.'`');
		$data = [];
		foreach($having as $key => $val){
			if(is_string($val)){
				$data[] = "({$tablename}.`{$key}`='{$val}')";
			}else{
				$count = count($val);
				if($count == 2)
					$data[] = "({$tablename}.`{$key}`{$val[0]}'{$val[1]}')";
				elseif($count == 3)
					$data[] = "({$val[2]}({$tablename}.`{$key}`){$val[0]}'{$val[1]}')";
			}
		}
		if(!empty($data))
			$this->group = "group by {$tablename}.`{$field}` having " . join(' and ', $data);
		else
			$this->group = "group by {$tablename}.`{$field}`";
	}

	/**
	 * 组装SQL语句
	 */
	public function _sql()
	{
		if(empty($this->table))return false;
		$field = '*';
		if(!empty($this->field) && is_array($this->field)){
			$field = [];
			foreach($this->field as $key => $val){
				$field[] = empty($key) ? $val : "{$val} as '{$key}'";
			}
			$field = join(',', $field);
		}
		$join = !empty($this->join) ? join(' ', $this->join) : '';
		$where = !empty($this->where) ? 'where ' . join(' and ', $this->where) : '';
		$group = !empty($this->group) ? $this->group : '';
		$order = !empty($this->order) ? 'order by ' . join(',', $this->order) : '';
		$limit = !empty($this->limit) ? $this->limit : '';
		$this->sql = "Select {$field} from `{$this->table}` {$this->asWord} {$join} {$where} {$group} {$order} {$limit} ";
		return $this->sql;
	}

	/**
	 * 主查询语句
	 */
	public function query(string $sql)
	{
		//组装SQL语句用于日志输出
		$_sql = $sql;
        foreach($this->params as $val){
            if(strlen($val) > 200)$val = preg_replace('/\s/m', ' ', substr($val, 0, 200) . '...');
            $_sql = preg_replace('/\?/', "'".$val."'", $_sql, 1);
        }

        //开始预处理查询
        if(!$rs = $this->link->prepare($sql)){
            trigger_error('数据执行失败！原因:['. $this->link->errno .']'. $this->link->error . ' SQL语句：' . $_sql, E_USER_WARNING);
            $this->params = [];
            return false;
        }

        //执行查询
        if(!$rs->execute($this->params, 600)){
            trigger_error('数据执行失败！原因:['. $this->link->errno .']'. $this->link->error . ' SQL语句：' . $_sql, E_USER_WARNING);
            $this->params = [];
            return false;
        }

        $this->params = [];
		if(U())U()->log('QUERY: '. $_sql);
		else{
            $msg = 'QUERY: '. $_sql;
            $time = round(microtime(true) * 10000);
            $msg .= ' [RunTime: '. ($time - $this->runtime)/10000 .'s]' . PHP_EOL;
            $this->runlogs .= $msg;
            $this->runtime = $time;
        }
		return $rs;
	}

	/**
	 * 主执行语句
	 * @param $sql 要执行的SQL语句
	 */
	public function execute(string $sql, int &$insert_id = null)
	{
		$_sql = $sql;
		foreach($this->params as $val){
			if(strlen($val) > 200)$val = preg_replace('/\s/m', ' ', substr($val, 0, 200) . '...');
			$_sql = preg_replace('/\?/', "'".$val."'", $_sql, 1);
		}

        //开始预处理查询
        if (!$rs = $this->link->prepare($sql)) {
            trigger_error('数据执行失败！原因:[' . $this->link->errno . ']' . $this->link->error . ' SQL语句：' . $_sql, E_USER_WARNING);
            $this->params = [];
            return false;
        }

        //执行查询
        if (!$rs->execute($this->params, 600)) {
            trigger_error('数据执行失败！原因:[' . $this->link->errno . ']' . $this->link->error . ' SQL语句：' . $_sql, E_USER_WARNING);
            $this->params = [];
            return false;
        }
        if($insert_id !== null)$insert_id = $this->link->insert_id;

		$this->params = [];
		if(U())U()->log('EXECUTE: '. $_sql);
        else{
            $msg = 'EXECUTE: '. $_sql;
            $time = round(microtime(true) * 10000);
            $msg .= ' [RunTime: '. ($time - $this->runtime)/10000 .'s]' . PHP_EOL;
            $this->runlogs .= $msg;
            $this->runtime = $time;
        }
		return true;
	}

    /**
     * 开启事务
     * @return mixed
     */
    public function beginTransaction()
    {
        self::$links['__db' . getcid()][$this->dbname] = $this->link;
        return $this->link->begin();
    }

    /**
     * 提交事务
     * @return mixed
     */
    public function commit()
    {
        unset(self::$links['__db' . getcid()][$this->dbname]);
        return $this->link->commit();
    }

    /**
     * 回滚事务
     * @return mixed
     */
    public function rollBack()
    {
        unset(self::$links['__db' . getcid()][$this->dbname]);
        return $this->link->rollback();
    }

	/**
	 * 查询记录集
	 * @param boolean $islock 是否锁表
	 * @return array 结果集数组
	 */
	public function select(bool $islock = false)
	{
		$sql = $this->_sql();
		if($islock)$sql .= ' for update;';
		if(!$rs = $this->query($sql))return false;
		$data = $rs->fetchall();
        $this->_reset();
		return $data;
	}

	/**
	 * 查询单条记录
	 * @param boolean $islock 是否锁表
	 */
	public function getone(bool $islock = false)
	{
		$this->limit(1);
		$rs = $this->select($islock);
		if(empty($rs))return false;
		else return $rs[0];
	}

	/**
	 * 插入记录
	 */
	public function insert(array $datas = [], bool $return = false, int $conflice = DB_INSERT_CONFLICT_NONE)
	{
		$_datas = [];
		foreach($this->fields as $key => $val){
			if($val !== null){
				$_datas[$key] = $val;
                $this->fields[$key] = null;
			}
		}

		$this->_filter($datas); //字段过滤
		$datas = array_merge($_datas, $datas);
		//组装数据
		$arr1 = array_keys($datas);
		$arr2 = array_values($datas);

		//组装SQL语句
        $sql = "Insert into ";
        if($conflice === DB_INSERT_CONFLICT_IGNORE)
            $sql = "Insert ignore into ";
        if($conflice === DB_INSERT_CONFLICT_REPLACE)
            $sql = "Replace into ";
		$this->sql = "{$sql}`{$this->table}` {$this->asWord} (`". join('`, `', $arr1) ."`) values (". join(", ", array_fill(0, count($arr1), '?')) .");";
		$this->params = $arr2;

		$insert_id = 0;
		$rs = $this->execute($this->sql, $insert_id);
		$this->_reset();
		if(!$rs)return false;
		if($return){
			return $insert_id;
		}else{
			return true;
		}
	}

    /**
     * 批量插入记录
     * @param array $dataAll 要插入的数据
     * @param bool $return_data 是否返回成功插入的数据，默认只返回插入条数
     * @return false|int
     */
    public function insertAll(array $data_all, bool $return_data = false, int $conflice = DB_INSERT_CONFLICT_NONE)
    {
        $_datas = $arrKey = $arrVal = [];
        foreach($this->fields as $key => $val){
            if($val !== null){
                $_datas[$key] = $val;
                $this->fields[$key] = null;
            }
        }

        //组装数据
        $_data_all = [];
        foreach($data_all as $datas){
            $this->_filter($datas);
            $datas = array_merge($_datas, $datas);
            if(empty($arrKey))$arrKey = array_keys($datas);
            $vals = [];
            foreach($arrKey as $key){
                $vals[] = $datas[$key] ?? null;
            }
            $arrVal[] = $vals;
            $_data_all[] = $datas;
        }

        $sql = "Insert into ";
        if($conflice === DB_INSERT_CONFLICT_IGNORE)
            $sql = "Insert ignore into ";
        if($conflice === DB_INSERT_CONFLICT_REPLACE)
            $sql = "Replace into ";
        $this->sql = "{$sql}`{$this->table}` (`". join('`, `', $arrKey) ."`) values ";
        $sql_arr = $val_arr = [];
        foreach($arrVal as $vals){
            $sql_arr[] = "(". join(", ", array_fill(0, count($arrKey), '?')) .")";
            $val_arr = array_merge($val_arr, $vals);
        }
        $this->sql .= join(',', $sql_arr) . ';';
        $this->params = $val_arr;

        $insert_id = 0;
        //执行
        $rs = $this->execute($this->sql, $insert_id);
        if(!$rs)return $return_data ? [] : 0;
        if($return_data){
            $first_id = $insert_id;
            $datas = [];
            foreach($_data_all as $row){
                $datas[] = array_merge(['id' => $first_id ++], $row);
            }
        }
        $this->_reset();
        return $return_data ? $datas : count($arrVal);
    }

	/**
 * 更新数据
 * @param array $datas
 * @return mixed
 */
    public function update(array $datas = [])
    {
        $_datas = [];
        foreach($this->fields as $key => $val){
            if($val !== null){
                $_datas[$key] = $val;
                $this->fields[$key] = null;
            }
        }

        //字段过滤
        $this->_filter($datas);
        $datas = array_merge($_datas, $datas);
        //组装数据
        $arr = [];
        foreach($datas as $key => $val){
            if(is_array($val)) {
                list($k, $v) = $val;
                switch (strtolower($k)) {
                    case '+':
                    case 'inc':
                        $arr[] = "`{$key}`=`{$key}`+{$v}";
                        break;
                    case '-':
                    case 'dec':
                        $arr[] = "`{$key}`=`{$key}`-{$v}";
                        break;
                    case '*':
                        $arr[] = "`{$key}`=`{$key}`*{$v}";
                        break;
                    case '/':
                        $arr[] = "`{$key}`=`{$key}`/{$v}";
                        break;
                    case 'exp':
                        $arr[] = "`{$key}`={$v}";
                        break;
                }
            }elseif ($val === null){
                $arr[] = "`{$key}`=null";
            }else{
                $val = addslashes($val);
                $arr[] = "`{$key}`='{$val}'";
            }
        }
        $where = '';
        if(!empty($this->where))$where = ' where (' . join(') and (', $this->where) . ')';
        //组装SQL语句
        $this->sql = "Update `{$this->table}` {$this->asWord} set " . join(',', $arr) . $where;
        $rs = $this->execute($this->sql);
        $this->_reset();
        return $rs;
    }

    /**
     * 批量更新数据
     * @param array $data_all 需要批量更新的数据 ['条件' => [name=>value, ...], ...]
     * @param string $field 需要做条件判断的字段名
     * @return int
     */
    public function updateAll(array $data_all, string $field = 'id')
    {
        if(!array_key_exists($field, $this->fields)){
            trigger_error('条件字段['. $field .']不是有效字段名', E_USER_WARNING);
            return 0;
        }

        $_datas = [];
        foreach($this->fields as $key => $val){
            if($val !== null){
                $_datas[$key] = $val;
                $this->fields[$key] = null;
            }
        }

        //组装数据
        $update_arr = [];
        $count = 0;
        foreach($data_all as $condition => $datas){
            $this->_filter($datas);
            $datas = array_merge($_datas, $datas);
            if(empty($datas))continue;
            //组装数据
            foreach($datas as $key => $val){
                if(is_array($val)) {
                    list($k, $v) = $val;
                    switch (strtolower($k)) {
                        case '+':
                        case 'inc':
                            $update_arr[$key][] = "when '{$condition}' then `{$key}`+{$v}";
                            break;
                        case '-':
                        case 'dec':
                            $update_arr[$key][] = "when '{$condition}' then `{$key}`-{$v}";
                            break;
                        case '*':
                            $update_arr[$key][] = "when '{$condition}' then `{$key}`*{$v}";
                            break;
                        case '/':
                            $update_arr[$key][] = "when '{$condition}' then `{$key}`/{$v}";
                            break;
                        case 'exp':
                            $update_arr[$key][] = "when '{$condition}' then {$v}";
                            break;
                    }
                }elseif ($val === null){
                    $update_arr[$key][] = "when '{$condition}' then null";
                }else{
                    $val = addslashes($val);
                    $update_arr[$key][] = "when '{$condition}' then '{$val}'";
                }
            }
            $count ++;
        }

        $arr = [];
        foreach($update_arr as $key => $row){
            $arr[] = "`{$key}`=case `{$field}` " . join(' ', $row) . " else `{$key}` end";
        }

        $where = '';
        if(!empty($this->where))$where = ' where (' . join(') and (', $this->where) . ')';
        //组装SQL语句
        $this->sql = "Update `{$this->table}` {$this->asWord} set " . join(',', $arr) . $where;
        $rs = $this->execute($this->sql);
        $this->_reset();
        return $rs ? $count : 0;
    }

	/**
	 * 删除记录
	 * @param null $limit
	 * @param null $length
	 * @return bool
	 */
	public function delete($limit = null, int $length = null)
	{
		$where = '';
		if(!empty($this->where))$where = ' where (' . join(') and (', $this->where) . ')';
		if(is_numeric($limit)){
			$limit = is_numeric($length) ? "limit {$limit}, {$length}" : "limit {$limit}";
			$this->sql = "Delete from `{$this->table}` {$this->asWord} " . $where . $limit;
		}else
			$this->sql = "Delete from `{$this->table}` {$this->asWord} " . $where;
		//执行删除操作
		$this->execute($this->sql);
		$this->_reset();
		return true;
	}

	/**
	 * SQL函数应用
	 * @param string $fun 函数名
	 * @param string $field 统计字段名
	 * @return int
	 */
	public function fun(string $fun, string $field = '*')
	{
		$this->field[] = "{$fun}({$field}) as num";
		$rs = $this->getone();
		return $rs['num'] ?? null;
	}

	/**
	 * 字段过滤
	 */
	private function _filter(&$datas)
	{
		$fields = array_keys($this->fields);
		if(is_array($datas)){
			$newDatas = [];
			foreach($datas as $key => $data){
				if(in_array($key, $fields))$newDatas[$key] = $data;
			}
			$datas = $newDatas;
			return $newDatas;
		}else{
			if(in_array($datas, $fields))return $datas;
			else return false;
		}
	}

	/**
	 * 清空执行痕迹
	 */
	private function _reset()
	{
		$this->field = null;
		$this->join = null;
		$this->where = null;
		$this->group = null;
		$this->order = null;
		$this->limit = null;
		if(isset(self::$links[$this->dbname]) && is_object(self::$links[$this->dbname])){
		    if(self::$links[$this->dbname]->isFull()){
                $this->link->close();
                $this->link = null;
            }else
                self::$links[$this->dbname]->push($this->link);
        }
	}

    public function __destruct()
    {
    }

}
