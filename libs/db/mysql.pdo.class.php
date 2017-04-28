<?php

namespace Root\Db;

class mysqlPDO
{
	//用于保存PDO连接
	private $link = null;
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

	/**
	 * 数据库连接
	 */
	public function __construct(array $config)
	{
		//获取数据库配置
		$this->config = $config;
		
		if(!empty($this->config) && count($this->config)>0){
			static $mysqllink = [];
			$worker_id = \Root::$serv->worker_id;
			if(!isset($mysqllink[$worker_id])){
				$dsn = "mysql:dbname={$this->config['DB_NAME']};host={$this->config['DB_HOST']};port={$this->config['DB_PORT']}";
				try {
					$mysqllink[$worker_id] = new \PDO($dsn, $this->config['DB_USER'], $this->config['DB_PASS']);
					$mysqllink[$worker_id]->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
				} catch (PDOException $e) {
					trigger_error('数据库连接失败！原因:'. $e->getMessage(), E_USER_ERROR);
				}
				$mysqllink[$worker_id]->query("set names {$this->config['DB_CHARSET']};");
			}
			$this->link = $mysqllink[$worker_id];
		}else{
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
		$rs = $this->link->query("SHOW COLUMNS FROM  `{$this->table}`");
		if(!$rs){
			return false;
		}
		while ($row = $rs->fetch()) {
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
		trigger_error("在数据表 {$this->dbname}.{$this->table} 中没有字段 {$name}", E_USER_ERROR);
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
					}elseif(is_string($val[0]) && in_array(strtolower($val[0]), ['=', '!=', '>', '<', '<>', 'like', 'not', 'not like']))
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
								elseif(is_string($v[0]) && in_array(strtolower($v[0]), ['in', 'not in'])){
									if(is_array($v[1]))$v[1] = "'" . join("','", $v[1]) . "'";
									$_arr[] = "({$tablename}.`{$key}` {$v[0]} ({$v[1]}))";
								}elseif(is_string($v[0]) && in_array(strtolower($v[0]), ['=', '!=', '>', '<', '<>', 'like', 'not', 'not like']))
									$_arr[] = "({$tablename}.`{$key}` {$v[0]} '{$v[1]}')";
							}else{
								$_arr[] = "({$tablename}.`{$key}` = ?)";
								$this->params[] = (string)$v;
							}
						}
						$arr[] = "(". join($char, $_arr) .")";
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
				if(empty($key))
					$this->order[] = "{$tablename}.{$val} {$asc}";
				else
					$this->order[] = "{$tablename}.{$key} {$val}";
			}
		}else
			$this->order[] = "{$tablename}.{$order} {$asc}";
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
	public function group(string $field, array $having = [])
	{
		if($this->_filter($field) !== false){
			$data = [];
			$tablename = $this->asWord ? : '`'.$this->table.'`';
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
				$this->group = "group by `{$field}` having " . join(' and ', $data);
			else
				$this->group = "group by `{$field}`";
		}
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
		$this->sql = "Select {$field} from `{$this->table}` {$this->asWord} {$join} {$where} {$group} {$order} {$limit};";
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
		foreach($this->params as $val){
			if(strlen($val) > 200)$val = preg_replace('/\s/m', ' ', substr($val, 0, 200) . '...');
			$_sql = preg_replace('/\?/', "'".$val."'", $_sql, 1);
		}

		try{
			//开始执行查询
			if(empty($this->params))
				$rs = $this->link->query($sql);
			else {
				$rs = $this->link->prepare($sql);
				$rs->execute($this->params);
			}
		}catch(\PDOException $e){
			//异常捕获
			$err = $e->errorInfo;
			$this->params = [];
			\Root::error("SQL查询出错！", ['content' => "错误原因：[{$err[0]}]{$err[2]}", 'sql' => $_sql], E_USER_WARNING);
			return false;
		}
		$this->params = [];
		if(\Root::$user)\Root::$user->log('QUERY: '. $_sql);
		return $rs;
	}

	/**
	 * 主执行语句
	 * @param $sql 要执行的SQL语句
	 * @param bool $checkTmp 是否检查临时表
	 */
	public function execute(string $sql, bool $checkTmp = true)
	{
		$_sql = $sql;
		foreach($this->params as $name => $val){
			if(strlen($val) > 200)$val = preg_replace('/\s/m', ' ', substr($val, 0, 200) . '...');
			$_sql = preg_replace('/\?/', "'".$val."'", $_sql, 1);
		}

		try{
			if(empty($this->params))
				$rs = $this->link->exec($sql);
			else {
				$rs = $this->link->prepare($sql);
				$rs = $rs->execute($this->params);
			}
		}catch(\PDOException $e){
			$err = $e->errorInfo;
			$this->params = [];
			\Root::error("SQL执行出错！", ['content' => "错误原因：[{$err[0]}]{$err[2]}", 'sql' => $_sql], E_USER_WARNING);
			return false;
		}

		if($checkTmp){
			$table = $this->_table;
			swoole_event_defer(function() use ($table){
				foreach(\Root::$worker->tmpTables as $tablename => $tmpTable){
					if(in_array($table, $tmpTable['tables']) && !in_array($tablename, \Root::$tmpTables)){
						\Root::$worker->send('updateTmpTables', $tablename);
					}
				}
			});
		}

		$this->params = [];
		if(\Root::$user)\Root::$user->log('EXECUTE: '. $_sql);
		return $rs;
	}

	/**
	 * 查询记录集
	 * @param boolean $cache 是否使用缓存（效率较低的语句建议开启缓存）
	 * @return array 结果集数组
	 */
	public function select(bool $cache = false)
	{
		$sql = $this->_sql();
		if($cache){
			$tablename = md5($sql);
			if(!array_key_exists($tablename, \Root::$worker->tmpTables)){
				$this->createTmp($tablename);
			}
			$sql = "Select * from `{$this->config['DB_PREFIX']}tmp_{$tablename}`";
		}
		$rs = $this->query($sql);
		if(is_bool($rs)){
			//异常拦截
			trigger_error("SQL查询出错！SQL: {$sql}", E_USER_WARNING);
			return false;
		}
		$rs = $rs->fetchall();
		$put = [];
		foreach($rs as $key => $row){
			foreach($row as $k => $r){
				if(!is_numeric($k))
					$put[$key][$k] = $r;
			}
		}
		return $put;
	}

	/**
	 * 查询单条记录
	 * @param boolean $cache 是否使用缓存（效率较低的语句建议开启缓存）
	 */
	public function getone(bool $cache = false)
	{
		$this->limit(1);
		$rs = $this->select($cache);
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

		$rs = $this->execute($this->sql);
		$this->_reset();
		if(!$rs)return false;
		if($return){
			return $this->link->lastInsertId();
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
			$arrKey = $arr;
			$arrVal[] = $datas;
		}

		//记录回滚事件
		$this->link->beginTransaction();
		//组装SQL语句
		$this->sql = "Insert into `{$this->table}` {$this->asWord} (`". join('`, `', $arrKey) ."`) values (:{". join('}, :{', $arrKey) ."});";
		try{
			$st = $this->link->prepare($this->sql);
			foreach($arrVal as $arr){
				$st->execute($arr);
				$sql = $this->sql;
				foreach($arr as $name => $val){
					if(strlen($val) > 200)$val = preg_replace('/\s/m', ' ', substr($val, 0, 200) . '...');
					$sql = str_replace(':{'.$name.'}', "'".$val."'", $sql);
				}
				if(\Root::$user)\Root::$user->log('EXECUTE: '. $sql);
			}
			$this->link->commit();
		}catch(\PDOException $e){
			$this->link->rollBack();
			$err = $e->errorInfo;
			\Root::error('INSERTALL语句出错!', ['content' => "错误原因：[{$err[0]}]{$err[2]}", 'sql' => $sql], E_USER_WARNING);
			return false;
		}
		$this->_reset();
		return $st->rowCount();
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
					default:continue;
				}
			}elseif(preg_match('/[\+\-\*\/][1-9][0-9]*/', $val))
				$arr[] = "`{$key}`=`{$key}`{$val}";
			else{
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
	}
	
	/**
	 * 创建临时表
	 */
	Public function createTmp(string $randname = null, string $sql = null)
	{
		if(is_string($randname)){
			$tablename = 'tmp_' . $randname;
		}elseif($randname === null){
			$tablename = 'tmp_' . $this->_table;
			$randname = $this->_table;
		}else{
			$tablename = md5($sql);
			$randname = $tablename;
		}
		if(empty($sql))$sql = empty($this->sql) ? $this->_sql() : $this->sql;

		$this->sql = "DROP TABLE IF EXISTS `{$this->config['DB_PREFIX']}{$tablename}`;CREATE TEMPORARY TABLE `{$this->config['DB_PREFIX']}{$tablename}` {$sql}";
		try{
			$this->link->exec("DROP TABLE IF EXISTS `{$this->config['DB_PREFIX']}{$tablename}`");
			$this->link->exec("CREATE TEMPORARY TABLE `{$this->config['DB_PREFIX']}{$tablename}` {$sql}");
		}catch(\PDOException $e){
			$err = $e->errorInfo;
			\Root::error('CREATE TEMPORARY 语句出错！', ['content' => "错误原因：[{$err[0]}]{$err[2]}", 'sql' => $this->sql], E_USER_WARNING);
			return false;
		}

		if(\Root::$user)\Root::$user->log('EXECUTE: '. $this->sql);
		\Root::$worker->tmpTables[$randname]['sql'] = $sql;
		return true;
	}

	/**
	 * 更新临时表
	 * @param $tablename 要更新的临时表名
	 */
	Public function updateTmp(string $tablename)
	{
		if(!array_key_exists($tablename, \Root::$worker->tmpTables))return false;
		$sql = \Root::$worker->tmpTables[$tablename]['sql'];
		$rs1 = $this->execute("DROP TABLE IF EXISTS `{$this->config['DB_PREFIX']}tmp_{$tablename}_copy`", false);
		$rs2 = $this->execute("CREATE TEMPORARY TABLE `{$this->config['DB_PREFIX']}tmp_{$tablename}_copy` {$sql}", false);
		if($rs1 && $rs2){
			$this->execute("DROP TABLE IF EXISTS `{$this->config['DB_PREFIX']}tmp_{$tablename}`", false);
			$rs4 = $this->execute("ALTER TABLE `{$this->config['DB_PREFIX']}tmp_{$tablename}_copy` RENAME TO `{$this->config['DB_PREFIX']}tmp_{$tablename}`", false);
			return $rs4;
		}
		return false;
	}

	public function __destruct()
	{
		$this->link = null;
	}

}