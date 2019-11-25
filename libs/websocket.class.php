<?php
/**
 * 核心websocket请求类
 * User: Dean.Lee
 * Date: 16/12/20
 */

namespace Root;

abstract class Websocket
{
    Static Public $fd = 0;
    Static Public $process = null;

    /**
     * 创建websocket服务端
     * @param $ip
     * @param $port
     * @return \swoole_websocket_server
     */
    Static Public function create(string $ip, int $port)
    {
        $table = [
            '__WEBSOCKET' => [
                '__total' => C('WEBSOCKET.max_connections'), //内存表总行数(2的指数), 2的10次方等于1024
                'connect_time' => 'int(4)', //时间戳,连接时间
                'server' => 'string(1024)', //连接者server
                'header' => 'string(1024)', //连接者server
                'get' => 'string(10240)',
                'mod_name' => 'string(20)',
                'cnt_name' => 'string(20)',
                'last_receive_time' => 'int(4)', //最后一次接收数据的时间戳
                'user' => 'string(20480)' //\Root::$user[getcid()]对象的串行化形式
            ],
            '__WEBSOCKET_TEAMS' => [
                '__total' => C('WEBSOCKET.max_connections'),
                'fds' => 'string(2048)'
            ]
        ];
        //创建内存表
        \Root\Table::create($table);

        $serv = new \swoole_websocket_server($ip, $port) or die('Swoole启动失败!');

        //设置websocket握手回调
        $serv->on('handshake', '\\Root\\Websocket::open');

        //设置websocket消息接收回调
        $serv->on('message', '\\Root\\Websocket::message');

        //设置websocket连接关闭回调
        $serv->on('close', '\\Root\\Websocket::close');

        return $serv;
    }

    /**
     * websocket客户端连接回调
     * @param $request
     * @param $response
     */
    Static Public function open($request, &$response)
    {
        if($request->header['upgrade'] != 'websocket')return;
        $response->header('Server', 'SSF-websocket');
        $response->header('Upgrade','websocket');
        $response->header('Connection','Upgrade');
        $websocketStr = $request->header['sec-websocket-key'].'258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
        $SecWebSocketAccept = base64_encode(sha1($websocketStr,true));
        $response->header('Sec-WebSocket-Accept', $SecWebSocketAccept);
        $response->header('Sec-WebSocket-Version','13');

        //配置路由
        $data = [
            'fd' => property_exists($request, 'fd') && !empty($request->fd) ? $request->fd : 0,
            'connect_time' => time(),
            'server' => property_exists($request, 'server') && !empty($request->server) ? $request->server : [],
            'header' => property_exists($request, 'header') && !empty($request->header) ? $request->header : [],
            'get' => property_exists($request, 'get') && !empty($request->get) ? $request->get : [],
            'mod_name' => C('HTTP.module'),
            'cnt_name' => C('HTTP.controller'),
            'last_receive_time' => time()
        ];
        $route = str_replace(C('HTTP.ext'), '', trim($request->server['request_uri'], '/'));
        if(!empty($route)){
            $route = explode('/', $route, 2);
            if(count($route) == 1){
                $data['cnt_name'] = $route[0];
            }elseif(count($route) == 2){
                $data['mod_name'] = $route[0];
                $data['cnt_name'] = $route[1];
            }
        }

        //获取参数
        $params = $data['get'];

        //实例化控制器,并运行方法
        $class_name = ucfirst($data['mod_name']) . "\\Websocket\\" . ucfirst($data['cnt_name']) . "Websocket";
        if(!isset(\Root::$map[$class_name]) || !class_exists($class_name)){
            $response->status(404);
            $response->end();
            return false;
        }
        \Root::$user[getcid()] = new \Root\User($data);

        //实例化open对象
        $ob = new $class_name;
        U()->log('INFO: Websocket instance completed');
        if($rs = $ob->_before_open() !== false){
            U()->log('INFO: _start() execution completed');
            $rs = call_user_func_array([$ob, '_open'], $params);
            U()->log('INFO: _open() execution completed');
            $ob->_end();
            U()->log('INFO: _end() execution completed');
        }else{
            U()->log('INFO: _start() execution finish');
            $response->status(401);
            $response->end();
            \Root::$user[getcid()] = null;
            return false;
        }

        if($rs === true){
            U()->db_links = [];
            U()->save();

            //将终端信息存入内存表
            $data['user'] = serialize(U());
            if(strlen($data['user']) > 10240){
                $response->status(500);
                $response->end();
                \Root::$user[getcid()] = null;
                return false;
            }
            if(!T('__WEBSOCKET')->set($request->fd, $data)){
                $response->status(401);
                $response->end();
                \Root::$user[getcid()] = null;
                return false;
            }
            $response->status(101);
            $response->end();
            \Root::$user[getcid()] = null;

            $fd = $request->fd;
            if(C('WEBSOCKET.heartbeat') == 1){
                \Root::$serv->tick(200, function ($time_id) use ($fd) {
                    if(\Root::$serv->isEstablished($fd))
                        \Root::$serv->push($fd, 0, 0x9);
                    else
                        \Root::$serv->clearTimer($time_id);
                });
            }elseif(C('WEBSOCKET.heartbeat') == 2){
                \Root::$serv->tick(1000, function($timer_id) use ($fd){
                    if(!\Root::$serv->exist($fd) || !\Root::$serv->isEstablished($fd)){
                        \Root::$serv->clearTimer($timer_id);
                        return;
                    }
                    static $i = 0;
                    static $failcount = 0;
                    $i ++;
                    if($i >= C('WEBSOCKET.heartbeat_check_interval')){
                        if(time() - T('__WEBSOCKET')->get($fd, 'last_receive_time') > C('WEBSOCKET.heartbeat_check_interval')){
                            \Root::$serv->push($fd, 'ping');
                            $failcount ++;
                        }else{
                            $failcount = 0;
                            $i = 0;
                        }
                        if($failcount > 3){
                            \Root::$serv->clearTimer($timer_id);
                            \Root::$serv->disconnect($fd, 1001, '心跳异常！');
                        }
                    }
                });
            }
            return true;
        }

        $response->status($rs?:401);
        $response->end();

        \Root::$user[getcid()] = null;
        return false;
    }

    /**
     * 接收客户端消息回调
     * @param $serv
     * @param $frame
     */
    Static Public function message(\swoole_server $serv, $frame)
    {
        T('__WEBSOCKET')->set($frame->fd, ['last_receive_time' => time()]);
        if($frame->data == 'ping'){
            //收到手动心跳包，则将该数据包发送回去
            if(C('WEBSOCKET.heartbeat') == 2){
                $serv->push($frame->fd, 'pong');
            }
            return;
        }

        $data = T('__WEBSOCKET')->get($frame->fd);
        if(empty($data['mod_name']) || empty($data['cnt_name']))return;
        \Root::$user[getcid()] = unserialize($data['user']);
        if($frame->data == 'pong'){
            \Root::$user[getcid()] = null;
            return;
        }

        //实例化控制器,并运行方法
        $class_name = ucfirst($data['mod_name']) . "\\Websocket\\" . ucfirst($data['cnt_name']) . "Websocket";
        if(!class_exists($class_name))return;

        ob_start();
        $ob = new $class_name;
        U()->log('INFO: Websocket instance completed');
        if($rs = $ob->_before_message($frame->data, $frame->fd) !== false){
            U()->log('INFO: _start() execution completed');
            $_data = $frame->data;
            if(C('WEBSOCKET.data_type') == 'json'){
                $_data = json_decode($frame->data, true);
                if(json_last_error() !== JSON_ERROR_NONE){
                    $_data = $frame->data;
                }
            }
            $ob->_message($_data);
            U()->log('INFO: _message() execution completed');
            $ob->_end();
            U()->log('INFO: _end() execution completed');
        }else{
            U()->log('INFO: _start() execution finish');
        }

        unset($ob);
        $data = ob_get_clean();
        if(!empty($data))self::push($frame->fd, $data);
        U()->save();

        \Root::$user[getcid()] = null;
        T('__PROCESS')->incr('worker_' . \Root::$serv->worker_id, 'receive');
    }

    /**
     * 客户端断开回调
     * @param $serv
     * @param $fd
     */
    Static Public function close(\swoole_server $serv, int $fd)
    {
        $data = T('__WEBSOCKET')->get($fd);
        if(empty($data))return;

        \Root::$user[getcid()] = unserialize($data['user']);

        //实例化控制器,并运行方法
        $class_name = ucfirst($data['mod_name']) . "\\Websocket\\" . ucfirst($data['cnt_name']) . "Websocket";
        if(!class_exists($class_name))return;

        $ob = new $class_name;
        U()->log('INFO: Websocket instance completed');
        if($rs = $ob->_before_end() !== false){
            U()->log('INFO: _start() execution completed');
            $ob->_close();
            U()->log('INFO: _close() execution completed');
            $ob->_end();
            U()->log('INFO: _end() execution completed');
        }else{
            U()->log('INFO: _start() execution finish');
        }

        unset($ob);
        U()->save();

        \Root::$user[getcid()] = null;
        T('__WEBSOCKET')->del($fd);
        T('__WEBSOCKET_TEAMS')->each(function($key, $row) use ($fd){
            $data = json_decode($row['fds'], true);
            if(in_array($fd, $data)){
                $data = array_merge(array_diff($data, [$fd]));
                T('__WEBSOCKET_TEAMS')->set($key, ['fds' => json_encode($data)]);
            }
            if(empty($data)){
                T('__WEBSOCKET_TEAMS')->del($key);
            }
        });
    }

    /**
     * 发送消息到客户端
     * @param $fd
     * @param $data
     * @param int $opcode
     * @param bool $finish
     */
    Static Public function push(int $fd, $data, int $opcode = 1, bool $finish = true)
    {
        //检测通道是否存在
        if(!\Root::$serv->exist($fd) || !\Root::$serv->isEstablished($fd)){
            trigger_error('通道['. $fd .']的客户端已经断开,无法发送消息!');
            return;
        }
        if(!is_string($data))$data = json_encode($data);
        \Root::$serv->push($fd, $data, $opcode, $finish);
        T('__PROCESS')->incr('worker_' . \Root::$serv->worker_id, 'sendout');
    }

    /**
     * 操作websocket分组
     * @param int|array $team_id
     * @param array|string $fds
     * @return bool|int
     */
    Static Public function team($team_id, $fds = '[NULL]')
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
     * 分组推送(异步)
     * @param $data
     * @param string $team_id
     */
    Static Public function pushTeam($data, string $team_id, array $except = [])
    {
        $fds = self::team($team_id);
        if(is_array($fds)){
            foreach($fds as $fd){
                if(!empty($except) && in_array($fd, $except))continue;
                if(empty($fd))continue;
                self::push($data, $fd);
            }
        }
    }
}