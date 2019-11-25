<?php
/**
 * 内存表
 * User: Dean.Lee
 * Date: 16/10/31
 */

namespace Root;

Class Table
{
    Static Public $table = [];
    Static Private $conf = [];
    Static Private $sizes = [];
    Private $_table = null;
    Private $tablename = null;
    //内存表默认保存时间12小时
    Private $expire = 12 * 60 * 60;

    /**
     * 创建内存表
     * @param array $conf 内存表配置数组
     * @return bool
     */
    Static Public function create(array $conf)
    {
        if(empty($conf))return false;
        foreach($conf as $tablename => $columns){
            if(!is_array($columns) || empty($columns) || !is_string($tablename))continue;
            if(isset(self::$table[$tablename]))continue;
            $total = isset($columns['__total']) ? $columns['__total'] : 10;
            if($total <= 0)continue;
            $configs = $columns;
            unset($columns['__total']);
            self::$table[$tablename] = new \Swoole\Table(pow(2, $total));
            if(isset($columns['__expire'])){
                if($columns['__expire'] > 0)self::$table[$tablename]->column('__datetime', \Swoole\Table::TYPE_INT, 5);
                unset($columns['__expire']);
            }
            self::$sizes[$tablename] = 0;
            foreach($columns as $name => $col){
                $type = \Swoole\Table::TYPE_STRING;
                $_type = 'string';
                $size = 256;
                $auto = '';
                if(is_array($col)){
                    if(isset($col['type'])){
                        switch($col['type']){
                            case 'int':
                                $type = \Swoole\Table::TYPE_INT;
                                $_type = 'int';
                                break;
                            case 'string':
                                $type = \Swoole\Table::TYPE_STRING;
                                $_type = 'string';
                                break;
                            case 'float':
                                $type = \Swoole\Table::TYPE_FLOAT;
                                $_type = 'float';
                                break;
                        }
                    }
                    if(isset($col['size']))$size = (int)$col['size'];
                    if(isset($col['auto'])){
                        if($col['auto'] == 'increment' && $col['type'] == 'int')$auto = 'increment';
                        if($col['auto'] == 'datetime' && in_array($col['type'],['int','string']))$auto = 'datetime';
                    }
                }elseif(is_string($col) && preg_match('/^(string|int|float)\((\d+)\)$/', $col, $arr)){
                    switch($arr[1]){
                        case 'int':
                            $type = \Swoole\Table::TYPE_INT;
                            $_type = 'int';
                            break;
                        case 'string':
                            $type = \Swoole\Table::TYPE_STRING;
                            $_type = 'string';
                            break;
                        case 'float':
                            $type = \Swoole\Table::TYPE_FLOAT;
                            $_type = 'float';
                            break;
                    }
                    $size = (int)$arr[2];
                }
                self::$sizes[$tablename] += $size;
                self::$table[$tablename]->column($name, $type, $size);
                $configs[$name] = [
                    'type' => $_type,
                    'size' => $size,
                    'auto' => $auto
                ];
            }
            self::$table[$tablename]->_atomic = new \Swoole\Atomic(0);
            self::$conf[$tablename] = $configs;
            self::$table[$tablename]->create();
        }
        return true;
    }

    /**
     * 获取内存表占用内存大小
     * @param string $tablename 指定内存表,默认所有内存表
     * @return int
     */
    Static Public function getSize(string $tablename = ''){
        if(!empty($tablename) && isset(self::$table[$tablename])){
            return count(self::$table[$tablename]) * self::$sizes[$tablename];
        }
        $sum = 0;
        foreach(self::$sizes as $tablename => $size){
            $sum += count(self::$table[$tablename]) * $size;
        }
        return $sum;
    }

    /**
     * 清空所有内存表
     * @param bool $onlyTimeout 是否仅清除超时的记录
     * @return boolean 是否成功
     */
    Static Public function clearAll($onlyTimeout = true)
    {
        foreach(self::$table as $tablename => $table){
            T($tablename)->clear($onlyTimeout);
        }
        return true;
    }

    /**
     * 移除内存表
     * @param string $tablename
     * @return bool
     */
    Static Public function remove(string $tablename)
    {
        $table = new self($tablename);
        $table->clear();
        unset(self::$table[$tablename]);
        return true;
    }

    /**
     * 移除所有内存表
     * @return bool
     */
    Static Public function removeAll()
    {
        self::clearAll(false);
        self::$table = [];
        return true;
    }

    /**
     * 选择并使用某内存
     * @param $tablename 表名
     */
    Public function __construct(string $tablename)
    {
        if(isset(self::$table[$tablename])){
            $this->tablename = $tablename;
            $this->_table = self::$table[$tablename];
            $expire = C('MEMORY_TABLE.' . $tablename . '.__expire');
            if(is_numeric($expire))$this->expire = $expire;
        }else{
            trigger_error($tablename . '内存表不存在!', E_USER_WARNING);
        }
    }

    /**
     * 获取内存表总行数
     * @return int
     */
    Public function count()
    {
        return count($this->_table);
    }

    /**
     * 获取内存表数据
     * @param string $key key
     * @param string $field 字段名
     * @return mixed
     */
    Public function get(string $key, string $field = null)
    {
        $data = $this->_table->get($key);
        if(!$data)return $data;
        $datas = [];
        foreach($data as $name => $value){
            $json = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $datas[$name] = $json;
            } else {
                $datas[$name] = $value;
            }
        }
        if(!empty($field)){
            if(!isset($datas[$field]))return false;
            return $datas[$field];
        }
        return $datas;
    }

    /**
     * 存入内存表数据
     * @param string $key
     * @param mixed $data
     * @return mixed
     */
    Public function set(string $key, array $data)
    {
        $this->_table->_atomic->add();
        $columns = self::$conf[$this->tablename];
        $datas = [];
        $time = time();
        foreach($columns as $name => $arr){
            if(isset($data[$name])){
                $_data = $data[$name];
                if(is_array($data[$name])){
                    $_data = json_encode($data[$name]);
                }
                $type = $arr['type'];
                $size = $arr['size'];
                if((strtolower($type) == 'int' && $size < strlen(decbin($_data))/8) || (strtolower($type) == 'string' && $size < strlen($_data))){
                    return false;
                }
                $datas[$name] = $_data;
                if($arr['auto'] == 'increment')$this->_table->_atomic->set($_data);
            }elseif($arr['auto'] == 'increment'){
                $datas[$name] = $this->_table->_atomic->get();
            }elseif($arr['auto'] == 'datetime'){
                if($arr['type'] == 'int')
                    $datas[$name] = $time;
                elseif($arr['type'] == 'string')
                    $datas[$name] = date('Y-m-d H:i:s', $time);
            }
        }
        if(empty($datas))return false;
        if($this->expire > 0)$datas['__datetime'] = time() + $this->expire;
        return $this->_table->set($key, $datas);
    }

    /**
     * 返回最后一条记录的原子ID
     * @return int
     */
    public function last_id()
    {
        return $this->_table->_atomic->get();
    }

    /**
     * 判断key是否存在
     * @param string $key
     * @return bool
     */
    Public function exist($key)
    {
        if(empty($key))return false;
        return $this->_table->exist($key);
    }

    /**
     * 自增
     * @param string $key
     * @param string $column
     * @param int $incrby
     * @return mixed
     */
    Public function incr(string $key, string $column, int $incrby = 1)
    {
        return $this->_table->incr($key, $column, $incrby);
    }

    /**
     * 自减
     * @param string $key
     * @param string $column
     * @param int $incrby
     * @return mixed
     */
    Public function decr(string $key, string $column, int $incrby = 1)
    {
        return $this->_table->decr($key, $column, $incrby);
    }

    /**
     * 删除单条
     * @param string $key
     * @return mixed
     */
    Public function del(string $key)
    {
        return $this->_table->del($key);
    }

    /**
     * 清空内存表
     * @param bool $onlyTimeout 是否仅删除超时的内存表记录
     * @return boolean 是否成功
     */
    Public function clear($onlyTimeout = true)
    {
        $keys = [];
        $time = time();
        foreach($this->_table as $key => $row){
            if(!$onlyTimeout || (isset($row['__datetime']) && $row['__datetime'] < $time))$keys[] = $key;
        }
        foreach($keys as $key){
            $this->del($key);
        }
        return true;
    }

    /**
     * 遍历内存表
     * @param callable $fun 遍历回调 参数:($key, $row)
     * @return bool
     */
    Public function each(callable $fun){
        foreach($this->_table as $key => $row){
            $fun($key, $row);
        }
        return true;
    }

    /**
     * 内存表加行锁
     * @param string $key
     */
    Public function lock(string $key){
        lock($this->tablename . '_' . $key);
    }

    /**
     * 内存表加行锁，非阻塞
     * @param string $key
     * @return bool 是否抢锁成功
     */
    Public function trylock(string $key){
        return lock($this->tablename . '_' . $key, 1);
    }

    /**
     * 解锁
     * @param string $key
     */
    Public function unlock(string $key){
        unlock($this->tablename . '_' . $key);
    }
}