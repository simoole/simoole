<?php

namespace Root;


class Session
{

    /**
     * 检测sess_id是否有效
     * @param $sess_id
     */
    Static Public function has(string $sess_id)
    {
        $rs = false;
        if(\Root::$conf['SESSION']['DRIVE'] == 'TABLE')
            $rs = T('__SESSION')->exist($sess_id);
        elseif(\Root::$conf['SESSION']['DRIVE'] == 'REDIS')
            $rs = getRedis()->exists('sess_' . $sess_id);
        return $rs;
    }

    /**
     * 清理过期的session
     */
    Static Public function cleanup()
    {
        if(\Root::$conf['SESSION']['DRIVE'] == 'TABLE'){
            $table = T('__SESSION');
            $keys = [];
            $time = time();
            $table->each(function(string $key, array $row) use(&$keys, $time){
                if($row['timeout'] < $time){
                    $keys[] = $key;
                }
            });
            foreach($keys as $key){
                $table->del($key);
            }
        }
    }

    private $conf = null;
    private $id = null;
    private $data = [];

    public function __construct(string $sess_id = null)
    {
        $this->id = $sess_id?:createCode(40, false);
        $this->conf = \Root::$conf['SESSION'];
        if($this->conf['DRIVE'] == 'TABLE') {
            if(($data = T('__SESSION')->get($this->id)) && $data['timeout'] > time()){
                $this->data = $data['data'];
            }else $this->data = [];
        }elseif($this->conf['DRIVE'] == 'REDIS'){
            $data = getRedis()->get('sess_' . $this->id);
            $data = json_decode($data, true);
            if(json_last_error() !== JSON_ERROR_NONE){
                $data = [];
            }
            $this->data = $data;
        }
        if(empty($this->data))$this->set();
    }

    public function __sleep()
    {
        return ['conf','id'];
    }

    public function __wakeup()
    {
        if($this->conf['DRIVE'] == 'TABLE') {
            $this->data = T('__SESSION')->get($this->id)?:[];
        }elseif($this->conf['DRIVE'] == 'REDIS'){
            $data = getRedis()->get('sess_' . $this->id);
            $data = json_decode($data, true);
            if(json_last_error() !== JSON_ERROR_NONE){
                $data = [];
            }
            $this->data = $data;
        }
        if(empty($this->data))$this->set();
    }

    /**
     * 获取session值
     * @param string $key
     * @return bool|mixed
     */
    public function get(string $key)
    {
        if(isset($this->data[$key]))
            return $this->data[$key];
        else return false;
    }

    /**
     * 设置session值
     * @param string $key
     * @param string $data
     * @return bool
     */
    public function set(string $key = '[null]', $data = '[null]')
    {
        if($key !== '[null]')$this->data[$key] = $data;
        if($this->conf['DRIVE'] == 'TABLE') {
            T('__SESSION')->set($this->id, [
                'timeout' => time() + $this->conf['EXPIRE'],
                'data' => $this->data
            ]);
        }elseif($this->conf['DRIVE'] == 'REDIS'){
            getRedis()->set('sess_' . $this->id, json_encode($this->data));
            getRedis()->expire('sess_' . $this->id, $this->conf['EXPIRE']);
        }
        return true;
    }

    /**
     * 删除指定session值
     * @param string $key
     * @return bool
     */
    public function del(string $key)
    {
        if(isset($this->data[$key])){
            unset($this->data[$key]);
            $this->set();
        }
        return true;
    }

    /**
     * 验证key是否存在
     * @param string $key
     * @return bool
     */
    public function exist(string $key)
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * 获取sessid
     * @return int|null|string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * 返回全部的session数据
     * @return array|mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * 保存session
     * @param array $data
     * @return bool
     */
    public function save(array $data)
    {
        $this->data = $data;
        $this->set();
        return true;
    }
}