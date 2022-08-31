<?php
/**
 * websocket ServerPush处理类
 * User: Dean.Lee
 * Date: 16/12/20
 */

namespace Simoole\Base;
use Simoole\Conf;

class Websocket
{
    protected $fd = null;
    //是否开启本次访问的二进制编码/解码
    public ?bool $is_binary = null;
    //是否开启本次访问的二进制加/解密（必须开启二进制编码）
    public ?bool $is_encrypt = null;
    //解密密钥
    public ?string $_skey = null;

    /**
     * 加/解密方法
     */
    public function _crypt(array $data)
    {
        $method = Conf::tcp('encrypt_func');
        return $method($data, $this->_skey);
    }

    public function __construct()
    {
        $this->fd = U('fd');
        if($this->is_binary === null)
            $this->is_binary = Conf::tcp('is_binary');
        if($this->is_encrypt === null)
            $this->is_encrypt = Conf::tcp('is_encrypt');
        if($this->_skey === null)
            $this->_skey = Conf::tcp('secret_key');
    }

    public function _before_open()
    {
        return true;
    }

    public function _before_message($data)
    {
        return true;
    }

    public function _before_close()
    {
        return true;
    }

    public function _open()
    {
        return false;
    }

    public function _message($data){}

    public function _close(){}

    public function _end()
    {
        return true;
    }

    public function _before_push($data, int $fd = null)
    {
        return [$data, $fd];
    }

    /**
     * 推送成功通知
     * @param $msg
     */
    protected function success($msg)
    {
        ob_clean();
        $this->push([
            'status' => '1',
            'data' => $msg
        ]);
    }

    /**
     * 推送失败通知
     * @param $msg
     */
    protected function error($msg)
    {
        ob_clean();
        $this->push([
            'status' => '0',
            'data' => $msg
        ]);
    }

    /**
     * 单一推送
     * @param $data
     * @param int $fd
     */
    protected function push($data, int $fd = null)
    {
        if(empty($fd))$fd = $this->fd;
        if(!$datas = $this->_before_push($data, $fd))return false;
        $data = array_change_value($datas[0]);
        $fd = $datas[1];
        if(!is_string($data))$data = json_encode($data);
        //是否做二进制传输
        if($this->is_binary){
            $arr = encodeASCII($data);
            if($this->is_encrypt){
                $arr = $this->_crypt($arr);
            }
            array_unshift($arr, 'C*');
            return \Simoole\Websocket::push($fd, call_user_func_array('pack', $arr), WEBSOCKET_OPCODE_BINARY);
        }else return \Simoole\Websocket::push($fd, $data);
    }

    /**
     * 全部推送(异步)
     * @param $data
     */
    protected function pushAll($data)
    {
        foreach(\Simoole\Root::$serv->connections as $fd){
            $this->push($data, $fd);
        }
    }

    /**
     * 获取通道客户端信息
     * @param int $fd
     * @return mixed
     */
    protected function getInfo($fd = null)
    {
        if(empty($fd))$fd = $this->fd;
        return Sub::send(['type' => MEMORY_WEBSOCKET_GET, 'fd' => $fd], null, true);
    }
}
