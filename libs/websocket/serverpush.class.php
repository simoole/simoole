<?php
/**
 * websocket ServerPush处理类
 * User: Dean.Lee
 * Date: 16/12/20
 */

namespace Root\Websocket;

class ServerPush
{
    Protected $fd = null;
    //Public $fds = [];

    Public function __construct()
    {
        $this->fd = \Root::$user->fd;
//        foreach(\Root::$serv->connections as $fd){
//            $this->fds[] = $fd;
//        }
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
     * 操作分组
     * @param int|array $team_id
     * @param array|string $fds
     * @return bool|int
     */
    protected function team($team_id, $fds = '[NULL]')
    {
        if(is_array($team_id)){
            $key = T('__WEBSOCKET_TEAMS')->count();
            T('__WEBSOCKET_TEAMS')->set($key, ['fds' => array_unique($team_id)]);
            return $key;
        }
        if($fds === '[NULL]'){
            if(T('__WEBSOCKET_TEAMS')->exist($team_id))
                return T('__WEBSOCKET_TEAMS')->get($team_id, 'fds');
            else return false;
        }elseif($fds === null || empty($fds)){
            if(T('__WEBSOCKET_TEAMS')->exist($team_id)){
                T('__WEBSOCKET_TEAMS')->del($team_id);
                return true;
            }else return false;
        }elseif(is_array($fds)){
            T('__WEBSOCKET_TEAMS')->set($team_id, ['fds' => array_unique($fds)]);
            return true;
        }elseif(is_numeric($fds)){
            $_fds = T('__WEBSOCKET_TEAMS')->get($team_id, 'fds');
            if(!$_fds)$_fds = [];
            if(in_array($fds, $_fds))return true;
            $_fds[] = $fds;
            T('__WEBSOCKET_TEAMS')->set($team_id, ['fds' => $_fds]);
            return true;
        }
    }

    /**
     * 单一推送
     * @param $data
     * @param int $fd
     */
    protected function push($data, int $fd = null)
    {
        if(empty($fd))$fd = $this->fd;
        if(!$datas = $this->_before_push($data, $fd)){
            return;
        }
        $data = $datas[0];
        $fd = $datas[1];
        \Root\Websocket::push($fd, $data);
    }

    /**
     * 分组推送(异步)
     * @param $data
     * @param string $team_id
     */
    protected function pushTeam($data, string $team_id, bool $pushself = true)
    {
        $fds = $this->team($team_id);
        if(is_array($fds)){
            foreach($fds as $fd){
                if(!$pushself && $fd == $this->fd)continue;
                if(empty($fd))continue;
                $this->push($data, $fd);
            }
        }

    }

    /**
     * 全部推送(异步)
     * @param $data
     */
    protected function pushAll($data)
    {
        foreach(\Root::$serv->connections as $fd){
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
        return T('__WEBSOCKET')->get($fd);
    }

}