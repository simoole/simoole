<?php
/**
 * 内存表
 * User: Dean.Lee
 * Date: 16/10/31
 */

namespace Simoole;

Class Table
{
    static public $table = [];
    static Private $conf = [];
    static Private $sizes = [];
    Private $_table = null;
    Private $tablename = null;
    //内存表默认保存时间12小时
    Private $expire = 12 * 60 * 60;

    /**
     * 创建内存表
     * @param array $conf 内存表配置数组
     * @return bool
     */
    static public function create(array $conf)
    {
        if(empty($conf))return false;
        foreach($conf as $tablename => $columns){
            if(!is_array($columns) || empty($columns) || !is_string($tablename))continue;
            //如果session驱动使用了redis，则不创建session内存表
            if(Conf::session('DRIVE') == 'REDIS' && $tablename == '__SESSION')continue;
            if(isset(self::$table[$tablename]))continue;
            $total = isset($columns['__total']) ? $columns['__total'] : 10;
            if($total <= 0)continue;
            $configs = $columns;
            unset($columns['__total']);
            unset($columns['__expire']);
            self::$table[$tablename] = new \Swoole\Table(pow(2, $total));
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
    static public function getSize(string $tablename = ''){
        if(!empty($tablename) && isset(self::$table[$tablename])){
            return self::$table[$tablename]->memorySize;
        }
        $sum = 0;
        foreach(self::$table as $table){
            $sum += $table->memorySize;
        }
        return $sum;
    }

    /**
     * 移除内存表
     * @param string $tablename
     * @return bool
     */
    static public function remove(string $tablename)
    {
        $table = new self($tablename);
        $table->clear();
        unset(self::$table[$tablename]);
        return true;
    }

    /**
     * 选择并使用某内存
     * @param $tablename 表名
     */
    public function __construct(string $tablename)
    {
        if(isset(self::$table[$tablename])){
            $this->tablename = $tablename;
            $this->_table = self::$table[$tablename];
            $expire = Conf::mtable($tablename, '__expire');
            if(is_numeric($expire))$this->expire = $expire;
        }else{
            trigger_error($tablename . '内存表不存在!', E_USER_WARNING);
        }
    }

    /**
     * 获取内存表总行数
     * @return int
     */
    public function count()
    {
        return count($this->_table);
    }

    /**
     * 获取内存表数据
     * @param string $key key
     * @param string $field 字段名
     * @return mixed
     */
    public function get(string $key, string $field = null)
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
    public function set(string $key, array $data)
    {
        $this->_table->_atomic->add();
        $columns = self::$conf[$this->tablename];
        $datas = [];
        $time = time();
        foreach($columns as $name => $arr){
            if(!is_array($arr))continue;
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
        if($this->expire > 0){
            Sub::send([
                'type' => MEMORY_TABLE_SET,
                'name' => $this->tablename,
                'key' => $key
            ], Conf::tcp('worker_num'));
        }
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
    public function exist($key)
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
    public function incr(string $key, string $column, int $incrby = 1)
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
    public function decr(string $key, string $column, int $incrby = 1)
    {
        return $this->_table->decr($key, $column, $incrby);
    }

    /**
     * 删除单条
     * @param string $key
     * @return mixed
     */
    public function del(string $key)
    {
        return $this->_table->del($key);
    }

    /**
     * 清空内存表
     * @return boolean 是否成功
     */
    public function clear()
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
    public function each(callable $fun){
        if($this->_table->count() > 0){
            foreach($this->_table as $key => $row){
                $res = $fun($key, $row);
                if($res === 'break')break;
            }
        }
        return true;
    }
}
