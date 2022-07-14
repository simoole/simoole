<?php
/**
 * 核心模型类
 * User: Dean.Lee
 * Date: 16/9/12
 */

namespace Simoole\Base;

Class Model {

    public $db = null;
    public $config = null;
    public $dbname = null;

    /**
     * 初始化数据库连接池
     */
    static public function _initialize()
    {
        $configs = \Simoole\Conf::database();
        foreach($configs as $name => $conf){
            //是否启用连接池
            if(!isset($conf['POOL']) || !$conf['POOL'])continue;
            $type = $conf['TYPE']?:'mysql';
            $classname = "\\Simoole\\Db\\{$type}CO";
            if(is_string($name))
                $classname::_initialize($conf, $name);
            else
                $classname::_initialize($conf);
        }
    }

    public function __construct(string $dbname = null)
    {
        $this->config = \Simoole\Conf::database($dbname ?: 'DEFAULT');
        if(empty($this->config) && !is_array($this->config)){
            throw new \Exception('您的数据库信息尚未配置, 请在config文件夹下的database.ini.php中配置好您的数据库信息！', 10001);
        }else{
            $this->dbname = isset($this->config['NAME']) ? $this->config['NAME'] : $dbname;
            $type = $this->config['TYPE']?:'mysql';
            $classname = "\\Simoole\\Db\\{$type}CO";
            $this->db = new $classname($this->config, $dbname ?: 'DEFAULT');
        }
    }

    /**
     * 选择数据表
     * @param string $table 数据表名
     * @return $this
     */
    public function table(string $table, string $asWord = '') : ?self
    {
        if(strpos($table, ' ') !== false){
            $arr = explode(' ', $table);
            $table = $arr[0];
            if(strtolower($arr[1]) == 'as')$asWord = $arr[2];
            else $asWord = $arr[1];
        }
        $table = preg_replace_callback('/(?!^)[A-Z]{1}/', function($result){
            return '_' . strtolower($result[0]);
        }, $table);
        $table = strtolower($table);
        if($this->db->table($table, $asWord)){
            return $this;
        }else{
            return null;
        }
    }

    /**
     * 输出数据表字段信息
     * @param $name 数据表字段名
     * @return string|boolean
     */
    public function __get(string $name)
    {
        return $this->db->$name;
    }

    /**
     * 配置字段的值，为insert/update做准备
     * @param string $name
     * @param string $value
     * @return boolean
     */
    public function __set(string $name, $value = null)
    {
        return $this->db->$name = $value;
    }

    /**
     * field 字段组装子句
     * @param mixed $field 字段或字段数组 ['字段', '字段' => '别名']
     * @param string $table 字段所在表名
     * @return $this
     */
    public function field($field, string $table = null) : self
    {
        $this->db->field($field, $table);
        return $this;
    }

    /**
     * 条件判断子句
     * @param mixed $condition 条件字符串或条件数组 ['字段' => ['比较符号', '值']]
     * @return $this
     */
    public function where($condition, string $table = null) : self
    {
        if(!empty($condition))$this->db->where($condition, $table);
        return $this;
    }

    /**
     * 分组查询group子句
     * @param mixed $field 用于分组的一个字段
     * @param array $having 用于分组筛选的条件
     * @return $this
     */
    public function group($field, array $having = []) : self
    {
        $this->db->group($field, $having);
        return $this;
    }

    /**
     * 关联组合
     * @param string $table 被关联的表名
     * @param array $condition 关联条件 on ...
     * @param string $type 关联类型,默认内联
     * @return $this
     */
    public function join(string $table, array $condition = [], string $type = 'inner') : self
    {
        $this->db->join($table, $condition, $type);
        return $this;
    }

    /**
     * order by组装子句
     * @param $order 组装字段
     * @param string $asc 排序方式 asc-desc
     * @return $this
     */
    public function order(string $order, string $asc = '', string $table = null) : self
    {
        if(strpos($order, '.') !== false){
            $arr = explode('.', $order);
            $table = $arr[0];
            $order = $arr[1];
        }
        $this->db->order($order, $asc, $table);
        return $this;
    }

    /**
     * limit子句
     * @param mixed $limit 偏移开始位置
     * @param int $length 读取条数
     * @return $this
     */
    public function limit($limit, int $length = null) : self
    {
        $this->db->limit($limit, $length);
        return $this;
    }

    /**
     * 查询记录集
     * @param  int $islock 是否上锁 0-不上锁 1-上排他锁(for update) 2-上共享锁(lock in share mode)
     * @return array 结果集数组
     */
    public function select(int $islock = 0)
    {
        $rs = $this->db->select($islock);
        return $rs;
    }

    /**
     * 查询单条记录
     * @param int $islock 是否上锁 0-不上锁 1-上排他锁(for update) 2-上共享锁(lock in share mode)
     * @return array 结果集数组
     */
    public function getone(int $islock = 0)
    {
        $rs = $this->db->getone($islock);
        return $rs;
    }

    /**
     * 返回单个字段组成的记录集
     * @param $name 字段名
     * @param bool $is_array 是否返回记录集
     * @return array|bool
     */
    public function getField(string $name, bool $is_array = false)
    {
        $table = '';
        if(strpos($name, '.') !== false){
            $arr = explode('.', $name);
            $table = $arr[0];
            $name = $arr[1];
        }
        $this->db->field($name, $table);
        if(!$is_array){
            $rs = $this->db->getone(false);
            if(isset($rs[$name])){
                return $rs[$name];
            }else{
                return false;
            }
        }else{
            $rs = $this->db->select(false);
            if(empty($rs))return [];
            $data = [];
            foreach($rs as $row){
                if(isset($row[$name])){
                    $data[] = $row[$name];
                }else{
                    return false;
                }
            }
            return $data;
        }
    }

    /**
     * 插入数据记录
     * @param array $datas 要插入的数据数组 ['字段' => '值',...]
     * @param bool $return 是否返回插入的ID
     * @param int $conflict 如何处理主键冲突
     *      DB_INSERT_CONFLICT_NONE-不处理
     *      DB_INSERT_CONFLICT_IGNORE-忽略冲突
     *      DB_INSERT_CONFLICT_REPLACE-覆盖冲突
     * @return array
     */
    public function insert(array $datas = [], bool $return = false, int $conflict = DB_INSERT_CONFLICT_NONE)
    {
        $rs = $this->db->insert($datas, $return, $conflict);
        return $rs;
    }

    /**
     * 多条插入记录
     * @param array $dataAll 要插入的数据数组
     * @param bool $return_data 是否返回插入后的数据，默认只返回插入的数量
     * @param int $conflict 如何处理主键冲突
     *      DB_INSERT_CONFLICT_NONE-不处理
     *      DB_INSERT_CONFLICT_IGNORE-忽略冲突
     *      DB_INSERT_CONFLICT_REPLACE-覆盖冲突
     * @return array|int
     */
    public function insertAll(array $dataAll, bool $return_data = false, int $conflict = DB_INSERT_CONFLICT_NONE)
    {
        $rs = $this->db->insertAll($dataAll, $return_data, $conflict);
        return $rs;
    }

    /**
     * 数据更新
     * @param array $datas 要更新的数据数组
     * @return array
     */
    public function update(array $datas = [])
    {
        $rs = $this->db->update($datas);
        return $rs;
    }

    /**
     * 数据批量更新
     * @param array $dataAll 要批量更新的数据数组 ['条件' => [name=>value, ...], ...]
     * @param string $field 条件字段
     * @return int
     */
    public function updateAll(array $dataAll = [], string $field = 'id')
    {
        $rs = $this->db->updateAll($dataAll, $field);
        return $rs;
    }

    /**
     * 数据删除
     * @param mixed $limit 偏移开始位置
     * @param int $length 数据条数
     * @return array
     */
    public function delete(int $limit = null, int $length = null)
    {
        $rs = $this->db->delete($limit, $length);
        return $rs;
    }

    /**
     * 统计数量子句
     * @param string $field 被统计的字段
     * @return int|boolean
     */
    public function count(string $field = null)
    {
        $rs = $this->db->fun('count', $field ?: '1');
        return $rs;
    }

    /**
     * 统计总量子句
     * @param string $field 被统计的字段
     * @return int|boolean
     */
    public function sum(string $field = null)
    {
        $rs = $this->db->fun('sum', $field ?: '*');
        return $rs;
    }

    /**
     * 统计平均值子句
     * @param string $field 被统计的字段
     * @return int|boolean
     */
    public function avg(string $field = null)
    {
        $rs = $this->db->fun('avg', $field ?: '*');
        return $rs;
    }

    /**
     * 统计最小值子句
     * @param string $field 被统计的字段
     * @return int|boolean
     */
    public function min(string $field = null)
    {
        $rs = $this->db->fun('min', $field ?: '*');
        return $rs;
    }

    /**
     * 统计最大值子句
     * @param string $field 被统计的字段
     * @return int|boolean
     */
    public function max(string $field = null)
    {
        $rs = $this->db->fun('max', $field ?: '*');
        return $rs;
    }

    /**
     * 字段自增
     * @param string $field 字段名
     * @param int $num 步长
     * @return int|boolean 影响记录行数
     */
    public function setInc(string $field, int $num = 1)
    {
        if($num > 0)
            $data = [$field => ['inc', $num]];
        elseif($num < 0)
            $data = [$field => ['dec', abs($num)]];
        else return true;
        $rs = $this->db->update($data);
        return $rs;
    }

    /**
     * 字段自减
     * @param string $field 字段名
     * @param int $num 步长
     * @return int|boolean 影响记录行数
     */
    public function setDec(string $field, int $num = 1)
    {
        if($num > 0)
            $data = [$field => ['dec', $num]];
        elseif($num < 0)
            $data = [$field => ['inc', abs($num)]];
        else return true;
        $rs = $this->db->update($data);
        return $rs;
    }

    /**
     * 获取执行的SQL语句
     * @param bool $create 是否重新组装
     * @return string
     */
    public function _sql(bool $create = false)
    {
        if($create)
            return $this->db->_sql();
        else
            return $this->db->sql;
    }

    /**
     * 查询sql语句
     * @param $sql 要执行的SQL语句
     * @return array|boolean
     */
    public function query(string $sql)
    {
        $sql = str_replace(['__PREFIX__', '__DBNAME__'], [$this->config['PREFIX'], $this->dbname], $sql);
        $rs = $this->db->query($sql);
        if(!$rs)return false;
        return $rs->fetchall();
    }

    /**
     * 执行SQL语句
     * @param $sql 要执行的SQL语句
     * @return boolean
     */
    public function execute(string $sql)
    {
        $sql = str_replace(['__PREFIX__', '__DBNAME__'], [$this->config['PREFIX'], $this->dbname], $sql);
        return $this->db->execute($sql);
    }

    /**
     * 开启事务
     * @return mixed
     */
    public function beginTransaction()
    {
        return $this->db->beginTransaction();
    }

    /**
     * 提交事务
     * @return mixed
     */
    public function commit()
    {
        return $this->db->commit();
    }

    /**
     * 回滚事务
     * @return mixed
     */
    public function rollBack()
    {
        return $this->db->rollBack();
    }

}

