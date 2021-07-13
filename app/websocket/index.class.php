<?php
/**
 * websocket类
 */

namespace App\Websocket;
use Simoole\Base\Websocket;

class IndexWebsocket extends Websocket
{
    /**
     * OPEN回调
     * return bool true允许连接 false报101错误禁止连接
     */
    public function _open()
    {
        return true;
    }

    /**
     * MESSAGE回调
     * string|array $data 接受到的数据
     */
    public function _message($data)
    {
    }

    /**
     * CLOSE回调
     */
    public function _close()
    {
    }
}
