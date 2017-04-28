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
    Public $fds = [];
    Public $teams = [];

    Public function __construct()
    {
        $this->fd = \Root::$user->fd;
        foreach(\Root::$serv->connections as $fd){
            $this->fds[] = $fd;
        }
        $this->teams = cache('websocket_client_teams');
    }

    Public function _start()
    {
        return true;
    }

    Public function _open()
    {
        return false;
    }

    Public function _message($data)
    {

    }

    Public function _close()
    {

    }

    Public function _end()
    {
        return true;
    }

    /**
     * 推送成功通知
     * @param $msg
     */
    Public function success($msg)
    {
        ob_clean();
        $this->send([
            'status' => '1',
            'info' => $msg
        ]);
    }

    /**
     * 推送失败通知
     * @param $msg
     */
    Public function error($msg)
    {
        ob_clean();
        $this->send([
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
    Public function team($team_id, $fds = '[NULL]')
    {
        if(is_array($team_id)){
            $keys = array_keys($this->teams);
            $key = $keys[count($keys) - 1] + 1;
            $this->teams[$key] = $team_id;
            return count($this->teams) - 1;
        }
        if(is_numeric($team_id)){
            if($fds === '[NULL]'){
                return isset($this->teams[$team_id]) ? $this->teams[$team_id] : false;
            }elseif($fds === null || empty($fds)){
                if(isset($this->teams[$team_id])){
                    unset($this->teams[$team_id]);
                    return true;
                }else
                    return false;
            }elseif(is_array($fds)){
                $this->teams[$team_id] = $fds;
                return true;
            }elseif(is_numeric($fds)){
                $this->teams[$team_id][] = $fds;
                return true;
            }
        }
    }

    /**
     * 单一推送
     * @param $data
     * @param int $fd
     */
    Public function push($data, $fd = null)
    {
        if(is_array($fd) && !empty($fd)){
            foreach($fd as $_fd){
                \Root\Websocket::push($_fd, $data);
            }
        }else{
            if(empty($fd))$fd = $this->fd;
            \Root\Websocket::push($fd, $data);
        }
    }

    /**
     * 分组推送(异步)
     * @param $data
     * @param int $team_id
     */
    Public function pushTeam($data, int $team_id)
    {
        foreach($this->teams[$team_id] as $fd){
            $this->push($data, $fd);
        }
    }

    /**
     * 全部推送(异步)
     * @param $data
     */
    Public function pushAll($data)
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
    Public function getInfo($fd = null)
    {
        if(empty($fd))$fd = $this->fd;
        return T('__WEBSOCKET')->get($fd);
    }

    Public function __destruct()
    {
        cache('websocket_client_teams', $this->teams);
    }

}