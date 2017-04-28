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
            self::$conf[$tablename] = $columns;
            $total = isset($columns['__total']) ? $columns['__total'] : 10;
            unset($columns['__total']);
            self::$table[$tablename] = new \swoole_table(pow(2, $total));
            self::$sizes[$tablename] = 0;
            foreach($columns as $name => $col){
                $type = \swoole_table::TYPE_STRING;
                $size = 256;
                if(preg_match('/^(string|int|float)\((\d+)\)$/', $col, $arr)){
                    switch($arr[1]){
                        case 'int':$type = \swoole_table::TYPE_INT;break;
                        case 'string':$type = \swoole_table::TYPE_STRING;break;
                        case 'float':$type = \swoole_table::TYPE_FLOAT;break;
                    }
                    $size = (int)$arr[2];
                }
                self::$sizes[$tablename] += $size;
                self::$table[$tablename]->column($name, $type, $size);
            }
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
     * @return boolean 是否成功
     */
    Static Public function clearAll()
    {
        foreach(self::$table as $table){
            $keys = [];
            foreach($table as $key => $row){
                $keys[] = $key;
            }
            foreach($keys as $key){
                $table->del($key);
            }
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
    Static Public function removeAll(){
        self::clearAll();
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
        }else{
            trigger_error($tablename . '内存表不存在!', E_USER_ERROR);
        }
    }

    /**
     * 获取内存表数据
     * @param string $key key
     * @return mixed
     */
    Public function get(string $key)
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
        $columns = self::$conf[$this->tablename];
        $datas = [];
        foreach($data as $name => $value){
            if(isset($columns[$name])){
                $json = json_encode($value);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $datas[$name] = $json;
                } else {
                    $datas[$name] = $value;
                }
            }
        }
        return $this->_table->set($key, $datas);
    }

    /**
     * 判断key是否存在
     * @param string $key
     * @return mixed
     */
    Public function exist(string $key)
    {
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
     * 删除单挑
     * @param string $key
     * @return mixed
     */
    Public function del(string $key)
    {
        return $this->_table->del($key);
    }

    /**
     * 清空内存表
     * @return boolean 是否成功
     */
    Public function clear()
    {
        $keys = [];
        foreach($this->_table as $key => $row){
            $keys[] = $key;
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
        if(gettype($fun) != 'object' || get_class($fun) != 'Closure')return false;
        foreach($this->_table as $key => $row){
            $fun($key, $row);
        }
        return true;
    }
}