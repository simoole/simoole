<?php
/**
 * 核心websocket请求类
 * User: Dean.Lee
 * Date: 16/12/20
 */

namespace Simoole;

abstract class Websocket
{
    static public $fds = [];
    static public $process = null;

    /**
     * 创建websocket服务端
     * @param $ip
     * @param $port
     * @return \swoole_websocket_server
     */
    static public function create(string $ip, int $port)
    {
        $serv = new \swoole_websocket_server($ip, $port) or die('Swoole启动失败!');

        //设置websocket握手回调
        $serv->on('handshake', '\\Simoole\\Websocket::open');

        //设置websocket消息接收回调
        $serv->on('message', '\\Simoole\\Websocket::message');

        //设置websocket连接关闭回调
        $serv->on('close', '\\Simoole\\Websocket::close');

        return $serv;
    }

    /**
     * websocket客户端连接回调
     * @param $request
     * @param $response
     */
    static public function open($request, &$response)
    {
        if($request->header['upgrade'] != 'websocket')return false;

        //配置路由
        $data = [
            'fd' => property_exists($request, 'fd') && !empty($request->fd) ? $request->fd : 0,
            'connect_time' => time(),
            'server' => property_exists($request, 'server') && !empty($request->server) ? $request->server : [],
            'header' => property_exists($request, 'header') && !empty($request->header) ? $request->header : [],
            'get' => property_exists($request, 'get') && !empty($request->get) ? $request->get : [],
            'last_receive_time' => time(),
            'worker_id' => Root::$worker->id,
            'route_group' => '',
            'route_path' => '',
            'class_name' => ''
        ];

        [$class_path, $route_path, $group_name] = Route::getPath($data['header']['http_host']??'', $data['server']['request_uri']??'/');

        if(empty($class_path)){
            throw new \Exception('路由['. ($data['server']['request_uri']??'/') .']匹配失败', 10116);
        }
        $data['route_group'] = $group_name;
        $data['route_path'] = $route_path;
        $pos = strpos($class_path, '@');
        if($pos !== false){
            $data['class_name'] = substr($class_path, 0, $pos);
        }else{
            $data['class_name'] = $class_path;
        }

        //实例化控制器,并运行方法
        $class_name = $data['class_name'];
        if(!class_exists($class_name)){
            $response->status(404);
            $response->end();
            return false;
        }
        Root::$user[getcid()] = new User($data);

        //实例化open对象
        $ob = new $class_name;
        U()->log('INFO: Websocket instance completed');
        if($ob->_before_open() !== false){
            U()->log('INFO: _start() execution completed');
            $rs = $ob->_open();
            U()->log('INFO: _open() execution completed');
            $ob->_end();
            U()->log('INFO: _end() execution completed');
        }else{
            U()->log('INFO: _start() execution finish');
            $response->status(401);
            $response->end();
            Root::$user[getcid()] = null;
            unset(Root::$user[getcid()]);
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
                Root::$user[getcid()] = null;
                unset(Root::$user[getcid()]);
                return false;
            }
            Sub::send(['type' => MEMORY_WEBSOCKET_SET, 'fd' => $request->fd, 'data' => $data]);
            self::$fds[] = $request->fd;

            $response->header('Server', 'simoole-websocket');
            $response->header('Upgrade','websocket');
            $response->header('Connection','Upgrade');
            $websocketStr = $request->header['sec-websocket-key'].'258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
            $SecWebSocketAccept = base64_encode(sha1($websocketStr,true));
            $response->header('Sec-WebSocket-Accept', $SecWebSocketAccept);
            $response->header('Sec-WebSocket-Version','13');
            $response->status(101);
            $response->end();

            Root::$user[getcid()] = null;
            unset(Root::$user[getcid()]);
            return true;
        }

        $response->status($rs?:401);
        $response->end();

        Root::$user[getcid()] = null;
        unset(Root::$user[getcid()]);
        return false;
    }

    /**
     * 接收客户端消息回调
     * @param $serv
     * @param $frame
     */
    static public function message(\swoole_server $serv, $frame)
    {
        //记录最后一次收到数据的时间点
        Sub::send(['type' => MEMORY_WEBSOCKET_SET, 'fd' => $frame->fd, 'data' => ['last_receive_time' => time()]]);

        $frame_data = $frame->data;

        //判断是否开启了手动心跳检测
        if(Conf::websocket('heartbeat') == 2 && strlen($frame_data) == 1){
            $frame_arr = unpack('C', $frame_data);
            if($frame_arr[1] == 1){
                //收到手动心跳包，则立即反馈
                $serv->push($frame->fd, pack('C', 2), WEBSOCKET_OPCODE_BINARY);
                return;
            }elseif($frame_arr[1] == 2) return;
        }

        $data = Sub::send(['type' => MEMORY_WEBSOCKET_GET, 'fd' => $frame->fd], null, true);
        //判断连接是否在本进程，不在则改绑
        if($data['worker_id'] != Root::$worker->id){
            Sub::send(['type' => MEMORY_WEBSOCKET_SET, 'fd' => $frame->fd, 'data' => ['worker_id' => Root::$worker->id]]);
            if(!in_array($frame->fd, self::$fds))self::$fds[] = $frame->fd;
        }
        //将会话实例唤醒
        Root::$user[getcid()] = unserialize($data['user']);

        //实例化控制器,并运行方法
        $class_name = $data['class_name'];
        if(!class_exists($class_name))return;

        ob_start();
        $ob = new $class_name;
        //是否进行二进制转换
        if($ob->is_binary){
            $frame_arr = unpack('C*', $frame_data);
            //是否解密
            if($ob->is_encrypt){
                $frame_arr = $ob->_crypt($frame_arr);
            }
            $frame_data = decodeASCII($frame_arr);
        }
        $_data = $frame_data;
        if(C('WEBSOCKET.data_type') == 'json'){
            $_data = json_decode($frame_data, true);
            if(json_last_error() !== JSON_ERROR_NONE){
                $_data = $frame_data;
            }
        }
        U()->log('INFO: Websocket instance completed');
        if($ob->_before_message($_data) !== false){
            U()->log('INFO: _start() execution completed');
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

        Root::$user[getcid()] = null;
        unset(Root::$user[getcid()]);
    }

    /**
     * 客户端断开回调
     * @param $serv
     * @param $fd
     */
    static public function close(\swoole_server $serv, int $fd)
    {
        $data = Sub::send(['type' => MEMORY_WEBSOCKET_GET, 'fd' => $fd], null, true);
        if(in_array($fd, self::$fds))unset(self::$fds[array_search($fd, self::$fds)]);
        if(empty($data))return;

        Root::$user[getcid()] = unserialize($data['user']);

        //实例化控制器,并运行方法
        $class_name = $data['class_name'];
        if(!class_exists($class_name))return;

        $ob = new $class_name;
        U()->log('INFO: Websocket instance completed');
        if($ob->_before_close() !== false){
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

        Root::$user[getcid()] = null;
        unset(Root::$user[getcid()]);
        Sub::send(['type' => MEMORY_WEBSOCKET_DEL, 'fd' => $fd]);
    }

    /**
     * 发送消息到客户端
     * @param $fd
     * @param $data
     * @param int $opcode
     * @param bool $finish
     */
    static public function push(int $fd, string $data, int $data_type = WEBSOCKET_OPCODE_TEXT)
    {
        //检测通道是否存在
        if(!Root::$serv->exist($fd) || !Root::$serv->isEstablished($fd)){
            trigger_error('通道['. $fd .']的客户端已经断开,无法发送消息!', E_USER_WARNING);
            return;
        }
        Root::$serv->push($fd, $data, $data_type);
    }

    /**
     * 心跳检测
     */
    static public function heartbeat()
    {
        //心跳定时器
        if(Conf::websocket('heartbeat') == 1){
            \Swoole\Timer::tick(200, function (){
                if(empty(self::$fds))return;
                foreach(self::$fds as $i => $fd){
                    $info = Root::$serv->getClientInfo($fd);
                    if(isset($info['uid']) && $info['uid'] != Root::$serv->worker_id)
                        unset(self::$fds[$i]);
                    elseif(Root::$serv->isEstablished($fd))
                        Root::$serv->push($fd, pack('C',1), WEBSOCKET_OPCODE_PING);
                }
            });
        }elseif(Conf::websocket('heartbeat') == 2){
            $ping = pack('C', 1);
            \Swoole\Timer::tick(1000, function() use ($ping) {
                static $loop = [];
                static $fail = [];
                if(empty(self::$fds))return;
                $conf = Conf::websocket();
                $data = Sub::send(['type' => MEMORY_WEBSOCKET_HEART], null, true);
                foreach($data as $fd => $last_time){
                    if (!Root::$serv->exist($fd) || !Root::$serv->isEstablished($fd)){
                        unset($loop[$fd]);
                        unset($fail[$fd]);
                        return;
                    }
                    if(!isset($loop[$fd])){
                        $loop[$fd] = 0;
                        $fail[$fd] = 0;
                    }
                    $loop[$fd] ++;
                    if ($loop[$fd] >= $conf['heartbeat_check_interval']) {
                        if (time() - $last_time > $conf['heartbeat_check_interval']) {
                            Root::$serv->push($fd, $ping, WEBSOCKET_OPCODE_BINARY);
                            $fail[$fd]++;
                        } else {
                            $loop[$fd] = 0;
                            $fail[$fd] = 0;
                        }
                        if ($fail[$fd] > 3) {
                            unset($loop[$fd]);
                            unset($fail[$fd]);
                            if(Root::$serv->exist($fd) && Root::$serv->isEstablished($fd))
                                Root::$serv->disconnect($fd, 1001, '心跳异常！');
                        }
                    }
                }
                foreach(self::$fds as $i => $fd){
                    $info = Root::$serv->getClientInfo($fd);
                    if(isset($info['uid']) && $info['uid'] != Root::$serv->worker_id)
                        unset(self::$fds[$i]);
                }
            });
        }
    }

}
