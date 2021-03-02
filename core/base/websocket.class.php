<?php
/**
 * websocket ServerPush处理类
 * User: Dean.Lee
 * Date: 16/12/20
 */

namespace Core\Base;

class Websocket
{
    Protected $fd = null;

    Public function __construct()
    {
        $this->fd = U('fd');
    }

    Public function _before_open()
    {
        return true;
    }

    Public function _open()
    {
        return false;
    }

    Public function _before_message($data, $fd)
    {
        return true;
    }

    Public function _message($data)
    {
    }

    Public function _close()
    {
    }

    Public function _before_end()
    {
        return true;
    }

    Public function _end()
    {
        return true;
    }

    Public function _before_push($data, int $fd = null)
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
            'info' => $msg
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
            'info' => $msg
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
        return \Core\Websocket::push($fd, $data);
    }

    /**
     * 全部推送(异步)
     * @param $data
     */
    protected function pushAll($data)
    {
        foreach(\Core\Root::$serv->connections as $fd){
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
