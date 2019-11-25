<?php

namespace Root\Db;

class mysqlCO
{
	//用于保存数据库长连接
	static private $links = [];
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

	//初始化数据库连接
	static public function _initialize(array $config, string $name = null)
    {
        if(!is_string($name))$name = 'DB_CONF';
        self::$links[$name] = new \Swoole\Coroutine\Channel($config['DB_POOL']);
        for($i=0; $i<$config['DB_POOL']; $i++){
            if(!$conn = self::_connect($config))return false;
            self::$links[$name]->push($conn);
        }
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
            'host' => $config['DB_HOST'],
            'port'    => $config['DB_PORT'],
            'user' => $config['DB_USER'],
            'password' => $config['DB_PASS'],
            'database' => $config['DB_NAME'],
            'timeout' => 300,
            'charset' => $config['DB_CHARSET'],
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
            $this->dbname = $name;
            $uid = '__db' . getcid();
            //判断是否有配置连接池
            if(isset($config['DB_POOL']) && $config['DB_POOL']){
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
            }else{
                //判断连接是否还在，还在则复用
                if(isset(self::$links[$uid]) && isset(self::$links[$uid][$name])) {
                    $this->link = self::$links[$uid][$name];
                }elseif($conn = self::_connect($config)){
                    //建立新的连接
                    $this->link = $conn;
                    self::$links[$uid][$name] = $conn;
                    //连接回收
                    \Swoole\Coroutine::defer(function() use ($uid, $name){
                        unset(self::$links[$uid][$name]);
                        if(empty(self::$links[$uid]))unset(self::$links[$uid]);
                        $this->link = null;
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
		$this->table = $this->config['DB_PREFIX'] . $table;
		$this->_table = $table;
		if(preg_match('/^[a-zA-Z]+\w*$/', $asWord))$this->asWord = $asWord;
		$rs = $this->query("SHOW COLUMNS FROM `{$this->table}`");
		if(!$rs)return false;
		while($row = $rs->fetch()) {
			$this->fields[] = $row;
			$field = $row['Field'];
			$this->$field = null;
		}
		return true;
	}

	/**
	 * 输出字段
	 */
	public function __get(string $name)
	{
		foreach($this->fields as $row){
			if($row['Field'] == $name)return $row;
		}
		return false;
	}
	
	/**
	 * 字段设置
	 */
	public function __set(string $name, $value = null)
	{
		foreach($this->fields as $row){
			$field = $row['Field'];
			if($field == $name)return $this->$field = $value;
		}
		trigger_error("在数据表 {$this->dbname}.{$this->table} 中没有字段 {$name}", E_USER_WARNING);
		return false;
	}

	/**
	 * where条件判断
	 */
	public function where($where, string $table = null)
	{
		//组装where
		if(is_array($where)){
			$arr = [];
			$tablename = $table?:($this->asWord?:'`'.$this->table.'`');
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
						if(in_array(strtolower($val[count($val)-1]), ['and', 'or'])){
							$char = " {$val[count($val)-1]} ";
							unset($val[count($val)-1]);
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
			$this->where = empty($this->where) ? $arr : array_merge($this->where, $arr);
		}elseif(!empty($where)){
			$this->where[] = "($where)";
		}
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
		if(empty($field) || $field === '*')return;
		if(!is_array($field))$field = explode(',', $field);
		$tablename = $table?:($this->asWord?:'`'.$this->table.'`');
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
				$fieldname = trim($fieldname);
				$pos = strpos($fieldname, '(') + 1;
				$fn = substr($fieldname, 0, $pos - 1);
				$fieldname = substr($fieldname, $pos, strpos($fieldname, ')') - $pos);
				$key = "{$fn}({$tablename}.`{$fieldname}`)";
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
		$table = $this->config['DB_PREFIX'] . $table;
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
		$this->_reset();
		return $this->sql;
	}

	/**
	 * 主查询语句
	 */
	public function query(string $sql)
	{
		//组装SQL语句用于日志输出
		$_sql = $sql;
        foreach($this->params as $i => $val){
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
        if(!$rs->execute($this->params)){
            trigger_error('数据执行失败！原因:['. $this->link->errno .']'. $this->link->error . ' SQL语句：' . $_sql, E_USER_WARNING);
            $this->params = [];
            return false;
        }

        $this->params = [];
		if(U())U()->log('QUERY: '. $_sql);
		return $rs;
	}

	/**
	 * 主执行语句
	 * @param $sql 要执行的SQL语句
	 */
	public function execute(string $sql, int &$insert_id = null)
	{
		$_sql = $sql;
		foreach($this->params as $i => $val){
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
        if (!$rs->execute($this->params)) {
            trigger_error('数据执行失败！原因:[' . $this->link->errno . ']' . $this->link->error . ' SQL语句：' . $_sql, E_USER_WARNING);
            $this->params = [];
            return false;
        }
        if($insert_id !== null)$insert_id = $this->link->insert_id;

		$this->params = [];
		if(U())U()->log('EXECUTE: '. $_sql);
		return true;
	}

    /**
     * 开启事务
     * @return mixed
     */
    Public function beginTransaction()
    {
        self::$links['__db' . getcid()][$this->dbname] = $this->link;
        return $this->link->begin();
    }

    /**
     * 提交事务
     * @return mixed
     */
    Public function commit()
    {
        unset(self::$links['__db' . getcid()][$this->dbname]);
        return $this->link->commit();
    }

    /**
     * 回滚事务
     * @return mixed
     */
    Public function rollBack()
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
	public function insert(array $datas = [], bool $return = false)
	{
		$_datas = [];
		foreach($this->fields as $row){
			$field = $row['Field'];
			if(isset($this->$field)){
				$_datas[$field] = $this->$field;
				unset($this->$field);
			}
		}

		$this->_filter($datas); //字段过滤
		$datas = array_merge($_datas, $datas);
		//组装数据
		$arr1 = array_keys($datas);
		$arr2 = array_values($datas);

		//组装SQL语句
		$this->sql = "Insert into `{$this->table}` {$this->asWord} (`". join('`, `', $arr1) ."`) values (". join(", ", array_fill(0, count($arr1), '?')) .");";
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
	 * 多条插入记录
	 */
	public function insertAll(array $dataAll)
	{
		$_datas = $arrKey = $arrVal = [];
		foreach($this->fields as $row){
			$field = $row['Field'];
			if(isset($this->$field)){
				$_datas[$field] = $this->$field;
				unset($this->$field);
			}
		}

		//组装数据
		foreach($dataAll as $datas){
			$this->_filter($datas);
			$datas = array_merge($_datas, $datas);
			$arr = array_keys($datas);
			if(empty($arrKey))$arrKey = $arr;
			if($arrKey != $arr)continue;
			$arrVal[] = array_values($datas);
		}

		//记录回滚事件
        $this->link->begin();
		//组装SQL语句
		$this->sql = "Insert into `{$this->table}` {$this->asWord} (`". join('`, `', $arr) ."`) values (". join(", ", array_fill(0, count($arr), '?')) .");";

		if(!$rs = $this->link->prepare($this->sql)){
            $this->link->rollBack();
            trigger_error('INSERTALL语句出错！原因:['. $this->link->errno .']'. $this->link->error . ' SQL语句：' . $this->sql, E_USER_WARNING);
            return false;
        }

        foreach($arrVal as $arr){
            $sql = $this->sql;
            foreach($arr as $i => $val){
                if(strlen($val) > 200)$val = preg_replace('/\s/m', ' ', substr($val, 0, 200) . '...');
                $sql = preg_replace('/\?/', "'".$val."'", $sql, 1);
            }
            if(U())U()->log('EXECUTE: '. $sql);

            //执行
            if (!$rs->execute($arr)) {
                $this->link->rollBack();
                trigger_error('INSERTALL语句出错！原因:['. $this->link->errno .']'. $this->link->error . ' SQL语句：' . $sql, E_USER_WARNING);
                return false;
            }
        }
        $this->link->commit();
        $this->_reset();
        return count($arrVal);
	}

	/**
	 * 更新数据
	 * @param array $datas
	 * @return mixed
	 */
	public function update(array $datas = [])
	{
		$_datas = [];
		foreach($this->fields as $row){
			$field = $row['Field'];
			if(isset($this->$field)){
				$_datas[$field] = $this->$field;
				unset($this->$field);
			}
		}
		//字段过滤
		$this->_filter($datas);
		$datas = array_merge($_datas, $datas);
		//组装数据
		$arr = $params = [];
		foreach($datas as $key => $val){
			if(is_array($val)){
				list($k, $v) = $val;
				switch(strtolower($k)){
					case '+':
					case 'inc':$arr[] = "`{$key}`=`{$key}`+{$v}";break;
					case '-':
					case 'dec':$arr[] = "`{$key}`=`{$key}`-{$v}";break;
					case '*':$arr[] = "`{$key}`=`{$key}`*{$v}";break;
					case '/':$arr[] = "`{$key}`=`{$key}`/{$v}";break;
					case 'exp':$arr[] = "`{$key}`={$v}";break;
				}
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
		//记录回滚事件
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
		return $rs['num'];
	}

	/**
	 * 字段过滤
	 */
	private function _filter(&$datas)
	{
		$fields = [];
		foreach($this->fields as $row){
			$fields[] = $row['Field'];
		}
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
		if(!isset(self::$links['__db' . getcid()]) || !isset(self::$links['__db' . getcid()][$this->dbname])){
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