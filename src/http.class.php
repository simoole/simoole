<?php

/**
 * 核心http请求类
 * User: Dean.Lee
 * Date: 16/12/20
 */

namespace Simoole;

class Http
{
    /**
     * Swoole启动回调
     * @param swoole_server $server
     */
    static public function start(\swoole_server $server)
    {
        swoole_set_process_name("[". APP_NAME ."] Master process in <". __ROOT__ .">");

        //实例启动后执行
        $method = C('APP.after_start');

        if(!empty($method))$method();
    }

    /**
     * Swoole正常关闭时回调
     * @param \swoole_server $server
     */
    static public function shutdown(\swoole_server $server)
    {
        //实例启动后执行
        $method = C('APP.after_stop');
        if(!empty($method))$method();
        foreach(glob(TMP_PATH . '*.pid') as $filename){
            $pid = @file_get_contents($filename);
            if(\swoole_process::kill($pid, 0))\swoole_process::kill($pid, 9);
            @unlink($filename);
        }
    }

    /**
     * 管理进程启动回调
     * @param swoole_server $server
     */
    static public function managerStart(\swoole_server $server)
    {
        file_put_contents(TMP_PATH . 'manager.pid', $server->manager_pid);
        swoole_set_process_name("[". APP_NAME ."] Manager process in <". __ROOT__ .">");
    }

    /**
     * 请求回调
     * @param $request
     * @param $response
     */
    static public function request(\swoole_http_request $request, \swoole_http_response &$response)
    {
        //配置路由
        $data = [
            'connect_time' => time(),
            'server' => property_exists($request, 'server') ? $request->server : [],
            'header' => property_exists($request, 'header') ? $request->header : [],
            'get' => property_exists($request, 'get') ? $request->get : [],
            'post' => property_exists($request, 'post') ? $request->post : [],
            'cookie' => property_exists($request, 'cookie') ? $request->cookie : [],
            'files' => property_exists($request, 'files') ? $request->files : [],
            'input' => $request->rawContent(),
            'route_group' => '',
            'route_path' => '',
            'class_name' => '',
            'action_name' => ''
        ];

        $host = '';
        if(isset($request->header['host']))$host = $request->header['host'];
        if(isset($request->header['http_host']))$host = $request->header['http_host'];
        [$class_path, $route_path, $group_name] = Route::getPath($host, $data['server']['request_uri']??'/');
        if($class_path === null){
            $response->end('');
            return;
        }
        if(empty($class_path)){
            trigger_error('路由['. ($data['server']['request_uri']??'/') .']匹配失败', E_USER_ERROR);
            $response->status(404);
            $response->end('[simoole]404 Not Found!');
            return;
        }
        $data['route_group'] = $group_name;
        $data['route_path'] = $route_path;
        $pos = strpos($class_path, '@');
        if($pos !== false){
            $data['class_name'] = substr($class_path, 0, $pos);
            $data['action_name'] = substr($class_path, $pos + 1);
        }else{
            $data['class_name'] = $class_path;
            $data['action_name'] = 'index';
        }

        //创建会话对象
        Root::$user[getcid()] = new User($data, $response);

        //执行本次会话任务
        self::run($data, $response);

        //会话结束回收内存
        Root::$user[getcid()] = null;
        unset(Root::$user[getcid()]);
    }

    /**
     * HTTP请求执行
     * @param $response
     */
    static public function run(array $data, \swoole_http_response &$response)
    {
        //检测控制器和指定方法是否存在
        $class_name = $data['class_name'];
        $action_name = $data['action_name'];

        if(!class_exists($class_name) || !method_exists($class_name, $action_name)){
            $response->status(404);
            $response->end('[simoole]404 Not Found!');
            return;
        }

        //会话任务开始，创建缓冲池
        ob_start();
        //实例化控制器
        $ob = new $class_name;
        //处理输入数据
        U()->handleData($ob);
        //判断是否开启自动try...catch，并进行对应处理
        if(Conf::app('auto_try')){
            try{
                $content = self::exec($ob, $action_name);
            }catch(\Exception $e){
                $ob->error($e->getMessage(), $e->getCode());
            }
        }else{
            $content = self::exec($ob, $action_name);
        }

        //获取缓冲池的输出内容
        if(empty($content) || $content === false || $content === true)$content = ob_get_clean();
        if(is_numeric($content))$content = strval($content);
        if(is_bool($content))$content = $content ? 'true' : 'false';

        //记录会话
        U()->save();

        //是否做二进制传输
        if($ob->is_binary){
            $response->header('Content-Type', 'application/octet-stream');
            $response->header('Content-Transfer-Encoding', 'binary');
            if(!is_string($content))$content = json_encode($content);
            $arr = encodeASCII($content);
            if($ob->is_encrypt){
                $arr = $ob->_crypt($arr);
            }
            array_unshift($arr, 'C*');
            $content = call_user_func_array('pack', $arr);
        }

        //最终输出
        $response->end($content);
    }

    static Private function exec(object $ob, string $action_name)
    {
        U()->log('INFO: Controller instance completed');
        //优先执行_start()方法
        if($ob->_start() !== false){
            U()->log('INFO: _start() execution completed');
            //执行指定方法
            $content = $ob->$action_name();
            U()->log('INFO: Action execution completed');
            //执行结束方法
            $ob->_end();
            U()->log('INFO: _end() execution completed');
        }else{
            U()->log('INFO: _start() execution finish');
        }
        return $content ?? null;
    }
}
