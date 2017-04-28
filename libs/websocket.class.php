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
    Static Public $user = [];
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
                '__total' => 10, //内存表总行数(2的指数), 2的10次方等于1024
                'connect_time' => 'int(4)', //时间戳,连接时间
                'server' => 'string(1024)', //连接者server
                'header' => 'string(1024)', //连接者server
                'get' => 'string(10240)',
                'mod_name' => 'string(10)',
                'cnt_name' => 'string(10)'
            ]
        ];
        //创建内存表
        \Root\Table::create($table);

        $serv = new \swoole_websocket_server($ip, $port) or die('Swoole启动失败!');

        //设置websocket握手回调
        $serv->on('handshake', 'Root\Websocket::open');

        //设置websocket消息接收回调
        $serv->on('message', 'Root\Websocket::message');

        //设置websocket连接关闭回调
        $serv->on('close', 'Root\Websocket::close');

        return $serv;
    }

    /**
     * websocket客户端连接回调
     * @param $request
     * @param $response
     */
    Static Public function open($request, &$response)
    {
        $response->header('Server', 'DeanPHP-websocket');
        $response->header('Upgrade','websocket');
        $response->header('Connection','Upgrade');
        $websocketStr = $request->header['sec-websocket-key'].'258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
        $SecWebSocketAccept = base64_encode(sha1($websocketStr,true));
        $response->header('Sec-WebSocket-Accept', $SecWebSocketAccept);
        $response->header('Sec-WebSocket-Version','13');

        //配置路由
        $data = [
            'fd' => property_exists($request, 'fd') ? $request->fd : [],
            'connect_time' => time(),
            'server' => property_exists($request, 'server') ? $request->server : [],
            'header' => property_exists($request, 'header') ? $request->header : [],
            'get' => property_exists($request, 'get') ? $request->get : [],
            'mod_name' => C('APPS.module'),
            'cnt_name' => C('APPS.controller')
        ];
        $route = str_replace(C('APPS.ext'), '', trim($request->server['request_uri'], '/'));
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
        $class_name = ucfirst($data['mod_name']) . '\\Websocket\\' . ucfirst($data['cnt_name']) . 'Websocket';
        if(!isset(\Root::$map[$class_name])){
            $response->status(404);
            return;
        }
        self::$user[$request->fd] = \Root::$user = new \Root\User($data);

        //实例化open对象
        $ob = new $class_name;
        \Root::$user->log('INFO: Websocket instance completed');
        if($rs = $ob->_start() !== false){
            \Root::$user->log('INFO: _start() execution completed');
            $rs = call_user_func_array([$ob, '_open'], $params);
            \Root::$user->log('INFO: _open() execution completed');
            $ob->_end();
            \Root::$user->log('INFO: _end() execution completed');
        }else{
            \Root::$user->log('INFO: _start() execution finish');
            $response->status(401);
            \Root::$user = null;
            return;
        }

        if($rs === true){
            //将终端信息存入内存表
            T('__WEBSOCKET')->set($request->fd, $data);
            $response->status(101);
            \Root::$user = null;
            return;
        }

        $response->status(401);
        \Root::$user = null;
    }

    /**
     * 接收客户端消息回调
     * @param $serv
     * @param $frame
     */
    Static Public function message(\swoole_server $serv, $frame)
    {
        T('__PROCESS')->incr(\Root::$serv->worker_pid, 'receive');
        \Root::$user = self::$user[$frame->fd];
        $data = T('__WEBSOCKET')->get($frame->fd);

        //实例化控制器,并运行方法
        $class_name = ucfirst($data['mod_name']) . '\\Websocket\\' . ucfirst($data['cnt_name']) . 'Websocket';

        ob_start();
        $ob = new $class_name;
        \Root::$user->log('INFO: Websocket instance completed');
        if($rs = $ob->_start() !== false){
            \Root::$user->log('INFO: _start() execution completed');
            $_data = $frame->data;
            if(C('WEBSOCKET.data_type') == 'json'){
                $_data = json_decode($frame->data, true);
                if(json_last_error() !== JSON_ERROR_NONE){
                    $_data = $frame->data;
                }
            }
            $ob->_message($_data);
            \Root::$user->log('INFO: _message() execution completed');
            $ob->_end();
            \Root::$user->log('INFO: _end() execution completed');
        }else{
            \Root::$user->log('INFO: _start() execution finish');
        }

        unset($ob);
        $data = ob_get_clean();
        if(!empty($data))self::push($frame->fd, $data);
        \Root::$user = null;
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

        \Root::$user = self::$user[$fd];

        //实例化控制器,并运行方法
        $class_name = ucfirst($data['mod_name']) . '\\Websocket\\' . ucfirst($data['cnt_name']) . 'Websocket';

        $ob = new $class_name;
        \Root::$user->log('INFO: Websocket instance completed');
        if($rs = $ob->_start() !== false){
            \Root::$user->log('INFO: _start() execution completed');
            $ob->_close();
            \Root::$user->log('INFO: _close() execution completed');
            $ob->_end();
            \Root::$user->log('INFO: _end() execution completed');
        }else{
            \Root::$user->log('INFO: _start() execution finish');
        }

        foreach($ob->teams as $key => $team){
            array_splice($ob->teams, array_search($fd, $team, true), 1);
            if(count($ob->teams[$key]) == 0)unset($ob->teams[$key]);
        }

        unset($ob);
        \Root::$user = null;
        unset(self::$user[$fd]);
        T('__WEBSOCKET')->del($fd);
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
        if(!\Root::$serv->exist($fd)){
            trigger_error('通道['. $fd .']的客户端已经断开,无法发送消息!');
            return;
        }
        if(!is_string($data))$data = json_encode($data);
        \Root::$serv->push($fd, $data, $opcode, $finish);
        T('__PROCESS')->incr(\Root::$serv->worker_pid, 'sendout');
    }

}