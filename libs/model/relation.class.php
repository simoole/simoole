<?php

namespace Root\Model;
use Root\Model;

Class RelationModel extends Model
{

	protected $relation = [
//		'A' => [
//			'table' => '<表名(无前缀)>',
//			'field' => [
//				'<字段名>',
//				'<字段名>' => '<字段别名>'
//			],
//			'where' => [
//				'<字段名>' => '<条件>'
//			]
//		],
//		'B' => [
//			'table' => '<数据库名>.<表名(无前缀)>',
//			'field' => [
//				'<字段名>'
//			],
//			'where' => [
//				<条件>
//			],
//			'on' => 'B.<字段名>=A.<字段名>',
//			'group' => '<字段名>'
//		]
	];
	private $_tmp_tablename = '';

	Public function __construct($dbname = null)
	{
		parent::__construct($dbname);
		if($tablename = $this->_join())$this->db[$this->dbname]->table($tablename);
	}

	/**
	 * 关联方法
	 */
	Private function _join()
	{
		if(empty($this->relation) || !is_array($this->relation)){
			trigger_error('指定的关联表不存在！', E_USER_WARNING);
			return false;
		}

		$classname = strrchr(get_class($this), "\\");
		$tablename = trim(substr($classname, 0, -5), "\\");

		if(array_key_exists($tablename, \Root::$worker->tmpTables)){
			return 'tmp_' . $tablename;
		}

		//先将所有on中的字段提取出来
		$on_fields = $_on_fields = [];
		foreach($this->relation as $word => $data){
			if(isset($data['on'])&&is_string($data['on'])){
				preg_match_all('/[A-Za-z0-9_\.]+/', $data['on'], $arr);
				foreach($arr[0] as $fd){
					$_arr = explode('.', $fd);
					$key = $word;
					$val = $_arr[0];
					if(isset($_arr[1])){
						$key = $_arr[0];
						$val = $_arr[1];
					}
					$on_fields[$key][] = $val;
					$_on_fields[$fd] = "{$key}_{$val}";
				}
			}
		}

		$relation = [];
		$fields = [];
		foreach($this->relation as $word => $data){
			$dbname = null;
			$table = $data['table'];
			if(strpos($table, '.')){
				$arr = explode('.', $table);
				$dbname = trim($arr[0], '`');
				$table = trim($arr[1], '`');
			}
			//创建关联临时表
			$m = M($table, $dbname);
			\Root::$worker->tmpTables[$tablename]['tables'][] = !empty($dbname) ? $dbname.'.'.$table : $table;

			$field = [];
			if(isset($data['field'])){
				if(is_array($data['field'])){
					foreach($data['field'] as $ky => $fd){
						if(is_numeric($ky))
							$field[$fd] = $fd;
						else
							$field[$ky] = $fd;
						$fields[] = $fd;
					}
				}else $fields[] = $field;
				$m->field($field);
			}
			$onstr = isset($data['on']) && is_string($data['on'])?$data['on']:'';
			$field = [];
			if(!empty($on_fields[$word])){
				if(!empty($onstr))
					$onstr = str_replace(array_keys($_on_fields), array_values($_on_fields), $onstr);
				foreach($on_fields[$word] as $fd){
					$field[$fd] = "{$word}_{$fd}";
				}
			}
			if(!empty($field))$m->field($field);

			if(isset($data['where']))$m->where($data['where']);
			if(isset($data['group'])){
				if(isset($data['having']))
					$m->group($data['group'], $data['having']);
				else
					$m->group($data['group']);
			}

			if(isset($data['order'])){
				if(is_array($data['order']))
					$m->order($data['order'][0], $data['order'][1]);
				else
					$m->order($data['order']);
			}
			$sql = trim(trim($m->_sql(true), ';'));
			$relation[] = [
				'table' => " ({$sql}) as {$word} ",
				'on' => $onstr,
				'type' => isset($data['type'])&&is_string($data['type'])?$data['type']:''
			];
		}
		//保存关联条件和类型
		$sql = '';
		foreach($relation as $i => $tb){
			if($i == 0){
				$fields = '`' . join('`,`', $fields) . '`';
				$sql = "Select {$fields} from {$tb['table']}";
			}else{
				$type = $tb['type'] ?: 'inner';
				$sql .= " {$type} join {$tb['table']} on {$tb['on']}";
			}
		}

		$rs = $this->createTmp($tablename, $sql);
		if($rs){
			\Root::$worker->tmpTables[$tablename]['isNeedUpdate'] = 0;
			$this->_tmp_tablename = $tablename;
			return 'tmp_' . $tablename;
		}else
			return false;
	}

	/**
	 * 更新临时表
	 */
	public function _update(){
		return $this->updateTmp($this->_tmp_tablename);
	}
	
}
