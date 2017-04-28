<?php

/**
 * 核心http请求类
 * User: Dean.Lee
 * Date: 16/12/20
 */

namespace Root;

class Http
{
    Static Public $data = null;

    /**
     * Swoole启动回调
     * @param swoole_server $server
     */
    Static Public function start(\swoole_server $server)
    {
        global $argv;
        \swoole_set_process_name("Master process in <{$argv[0]}>");
        //绑定状态事件
        \swoole_process::signal(SIGUSR1, '\Root\Http::status');
        //绑定重载事件
        \swoole_process::signal(SIGUSR2, function() use ($server){
            $server->reload();
        });
        T('__PROCESS')->set($server->master_pid, [
            'id' => 0,
            'type' => 0,
            'pid' => $server->master_pid,
            'receive' => 0,
            'sendout' => 0,
            'memory_usage' => memory_get_usage(true),
            'memory_used' => memory_get_usage()
        ]);
    }

    /**
     * 管理进程启动回调
     * @param swoole_server $server
     */
    Static Public function managerStart(\swoole_server $server)
    {
        global $argv;
        $num = count(glob(TMP_PATH . 'manager_*.pid'));
        @file_put_contents(TMP_PATH . 'manager_'. $num .'.pid', $server->manager_pid);
        \swoole_set_process_name("Manager[{$num}] process in <{$argv[0]}>");
        T('__PROCESS')->set($server->manager_pid, [
            'id' => $num,
            'type' => 1,
            'pid' => $server->manager_pid,
            'receive' => 0,
            'sendout' => 0,
            'memory_usage' => memory_get_usage(true),
            'memory_used' => memory_get_usage()
        ]);
    }

    /**
     * 处理服务状态信号
     */
    Static Public function status()
    {
        $memory_usage = memory_get_usage(true);
        $memory_used = memory_get_usage();
        T('__PROCESS')->set(\Root::$serv->master_pid, [
            'memory_usage' => $memory_usage,
            'memory_used' => $memory_used
        ]);

        //生成报告
        $datas = [];
        $datas[] = "[主进程]";
        $datas[] = "进程ID: " . \Root::$serv->master_pid;
        $datas[] = "内存占用: " . $memory_usage/1024 . 'kb';
        $datas[] = "内存使用: " . $memory_used/1024 . 'kb';
        $datas[] = '';
        $datas[] = "[内存表]";
        $sum = 0;
        foreach(\Root\Table::$table as $tablename => $table){
            $size = \Root\Table::getSize($tablename)/1024;
            $datas[] = "<{$tablename}> - " . count($table) . "条数据 - 占用{$size}kb";
            $sum += $size;
        }
        $datas[] = "内存表总占用: {$sum}kb";
        $datas[] = '';
        $sum_usage = $memory_usage + $sum;
        $sum_used = $memory_used;
        $sum_receive = $sum_sendout = $sum_task = 0;
        foreach(glob(TMP_PATH . '*_*.pid') as $filename){
            $pid = @file_get_contents($filename);
            if(!strpos($filename, 'manager') && !strpos($filename, 'task')){
                \swoole_process::kill($pid, SIGUSR1);
                usleep(10000);
            }
            $data = T('__PROCESS')->get($pid);
            if(empty($data))$datas[] = "[进程 {$pid} 异常!]" . PHP_EOL;
            switch($data['type']){
                case 1:
                    $datas[] = "[管理进程][ID.{$data['id']}]";
                    $datas[] = "进程ID: " . $data['pid'];
                    $datas[] = "内存占用: " . $data['memory_usage']/1024 . 'kb';
                    $datas[] = "内存使用: " . $data['memory_used']/1024 . 'kb';
                    $sum_usage += $data['memory_usage'];
                    $sum_used += $data['memory_used'];
                    break;
                case 2:
                    $datas[] = "[工作进程][ID.{$data['id']}]";
                    $datas[] = "进程ID: " . $data['pid'];
                    $datas[] = "接收数据包: {$data['receive']}个";
                    $datas[] = "发送数据包: {$data['sendout']}个";
                    $datas[] = "内存占用: " . $data['memory_usage']/1024 . 'kb';
                    $datas[] = "内存使用: " . $data['memory_used']/1024 . 'kb';
                    $sum_receive += $data['receive'];
                    $sum_sendout += $data['sendout'];
                    $sum_usage += $data['memory_usage'];
                    $sum_used += $data['memory_used'];
                    break;
                case 3:
                    $datas[] = "[任务进程][ID.{$data['id']}]";
                    $datas[] = "进程ID: " . $data['pid'];
                    $datas[] = "处理任务: {$data['receive']}个";
                    $datas[] = "内存占用: " . $data['memory_usage']/1024 . 'kb';
                    $datas[] = "内存使用: " . $data['memory_used']/1024 . 'kb';
                    $sum_task += $data['receive'];
                    $sum_usage += $data['memory_usage'];
                    $sum_used += $data['memory_used'];
                    break;
                case 4:
                    $datas[] = "[子进程][ID.{$data['id']}]";
                    $datas[] = "进程ID: " . $data['pid'];
                    $datas[] = "内存占用: " . $data['memory_usage']/1024 . 'kb';
                    $datas[] = "内存使用: " . $data['memory_used']/1024 . 'kb';
                    $sum_usage += $data['memory_usage'];
                    $sum_used += $data['memory_used'];
                    break;
            }
            $datas[] = '';
        }
        $datas[] = "接受数据包总量:{$sum_receive} 发送数据包总量:{$sum_sendout}";
        if(!empty($sum_task))$datas[] = "处理任务总量:" . $sum_task;
        $datas[] = "内存占用总量:" . $sum_usage/1024 . 'kb ' . "内存使用总量:" . $sum_used/1024 . 'kb ';
        @file_put_contents(TMP_PATH . 'status.info', join(PHP_EOL, $datas));
    }

    /**
     * 请求回调
     * @param $request
     * @param $response
     */
    Static Public function request($request, &$response)
    {
        T('__PROCESS')->incr(\Root::$serv->worker_pid, 'receive');
        //配置路由
        $data = [
            'connect_time' => time(),
            'server' => property_exists($request, 'server') ? $request->server : [],
            'header' => property_exists($request, 'header') ? $request->header : [],
            'get' => property_exists($request, 'get') ? $request->get : [],
            'post' => property_exists($request, 'post') ? $request->post : [],
            'cookie' => property_exists($request, 'cookie') ? $request->cookie : [],
            'input' => $request->rawContent(),
            'mod_name' => C('APPS.module'),
            'cnt_name' => C('APPS.controller'),
            'act_name' => C('APPS.action')
        ];
        $route = str_replace(C('APPS.ext'), '', trim($data['server']['request_uri'], '/'));

        if(!empty($route)){
            $route = explode('/', $route);
            if(count($route) == 1){
                $data['act_name'] = $route[0];
            }elseif(count($route) == 2){
                $data['cnt_name'] = $route[0];
                $data['act_name'] = $route[1];
            }elseif(count($route) == 3){
                $data['mod_name'] = $route[0];
                $data['cnt_name'] = $route[1];
                $data['act_name'] = $route[2];
            }
        }

        self::$data = $data;
        \Root::$user = new User($data, $response);
        self::run($request, $response);
        //回收内存
        self::$data = null;
        \Root::$user = null;
    }

    /**
     * HTTP请求执行
     * @param $request
     * @param $response
     */
    Static Public function run($request, &$response)
    {
        //检查缓存
        if(C('HTTP.cache_time')){
            $key = md5(json_encode([$request->server['request_uri'], $_POST, self::$data['input'], $_FILES]));
            if($pagedata = cache('page_' . $key)){
                if(C('HTTP.gzip'))$response->gzip(C('HTTP.gzip'));
                $response->end($pagedata);
                return;
            }
        }

        //获取参数
        $params = I('get.param', []);

        //实例化控制器,并运行方法
        $class_name = ucfirst(self::$data['mod_name']) . '\\Controller\\' . ucfirst(self::$data['cnt_name']) . 'Controller';
        if(!isset(\Root::$map[$class_name]) || !in_array(self::$data['act_name'], \Root::$map[$class_name]['methods'])){
            $response->status(404);
            $response->end('[DeanPHP]404 Not Found!');
            return;
        }

        ob_start();
        $ob = new $class_name;
        \Root::$user->log('INFO: Controller instance completed');
        if($rs = $ob->_start() !== false){
            \Root::$user->log('INFO: _start() execution completed');
            $content = call_user_func_array([$ob, self::$data['act_name']], $params);
            \Root::$user->log('INFO: Action execution completed');
            $ob->_end();
            \Root::$user->log('INFO: _end() execution completed');
        }else{
            \Root::$user->log('INFO: _start() execution finish');
        }

        if(empty($content))$content = ob_get_clean();

        //记录缓存
        if(C('HTTP.cache_time')){
            $key = md5(json_encode([$request->server['request_uri'], $_POST, self::$data['input'], $_FILES]));
            cache('page_' . $key, $content, C('HTTP.cache_time'));
        }

        if(C('HTTP.gzip'))$response->gzip(C('HTTP.gzip'));

        \Root::$user->sessionSave();

        $response->end($content);
        T('__PROCESS')->incr(\Root::$serv->worker_pid, 'sendout');
    }
}