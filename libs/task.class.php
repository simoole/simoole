<?php
/**
 * 核心Task类
 * User: Dean.Lee
 * Date: 16/12/20
 */

namespace Root;

class Task
{
    Static Public $callback = '';

    /**
     * 任务投递回调
     * @param \swoole_server $serv
     * @param int $task_id
     * @param int $worker_id
     * @param string $data
     */
    Static Public function start(\swoole_server $serv, int $task_id, int $worker_id, $data)
    {
        T('__PROCESS')->incr($serv->worker_pid, 'receive');

        $json = $data;

        //配置路由
        $data = [
            'connect_time' => time(),
            'server' => $json['server'],
            'header' => $json['header'],
            'get' => $json['get'],
            'post' => $json['post'],
            'cookie' => $json['cookie'],
            'input' => '',
            'mod_name' => C('HTTP.module'),
            'cnt_name' => C('HTTP.controller'),
            'act_name' => C('HTTP.action')
        ];
        $route = self::$callback;

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

        \Root::$user = new User($data);

        //实例化控制器,并运行方法
        $class_name = ucfirst($data['mod_name']) . "\\Controller\\" . ucfirst($data['cnt_name']) . "Controller";
        $actname = $data['act_name'];
        if(!isset(\Root::$map[$class_name]) || !in_array($actname, \Root::$map[$class_name]['methods'])){
            $serv->finish(json_encode(['status' => 0, 'info' => '[SSF]404 Not Found!']));
            return;
        }

        ob_start();
        $ob = new $class_name;
        \Root::$user->log('INFO: Controller instance completed');
        if($rs = $ob->_start() !== false){
            \Root::$user->log('INFO: _start() execution completed');
            $content = $ob->$actname($json['data']);
            \Root::$user->log('INFO: Action execution completed');
            $ob->_end();
            \Root::$user->log('INFO: _end() execution completed');
        }else{
            \Root::$user->log('INFO: _start() execution finish');
        }

        if(empty($content))$content = ob_get_clean();

        \Root::$user->save();

        $serv->finish(json_encode(['status' => 1, 'info' => $content]));

        //回收内存
        \Root::$user = null;

        T('__PROCESS')->set($serv->worker_pid, [
            'memory_usage' => memory_get_usage(true),
            'memory_used' => memory_get_usage()
        ]);
    }

    Static Public function finish(\swoole_server $serv, int $task_id, string $data){}

    /**
     * 获取任务实例
     * @param string $name
     * @return bool|task
     */
    Static Public function get(string $name)
    {
        static $instance = [];
        if(!isset($instance[$name])){
            if(array_key_exists($name, C('TASK')))
                $instance[$name] = new self($name);
            else
                return false;
        }
        return $instance[$name];
    }

    Private function __construct($name)
    {
        $this->ids = \Root::$tasks[$name]['ids'];
        $this->name = $name;
    }

    /**
     * 添加任务
     * @param mixed $data 投递的数据
     * @param callable|null $callback
     */
    Public function add($data, callable $callback = null)
    {
        static $i = 0;
        $user = \Root::$user;
        $datas = [
            'server' => array_diff_key($user->server, $user->header),
            'header' => $user->header,
            'get' => $user->get,
            'post' => $user->post,
            'cookie' => $user->cookie,
            'data' => $data
        ];
        $name = $this->name;
        \Root::$serv->task($datas, $this->ids[$i], function(\swoole_server $serv, $task_id, $data) use ($name, $callback){
            $json = json_decode($data, true);
            if($json['status']){
                if($callback !== null)$callback($json['info'], $task_id);
                $code = '['. date('Y-m-d H:i:s') .'][任务执行状态: SUCCESS]' . PHP_EOL;
                $code .= var_export($json['info'], true) . PHP_EOL;
            }else{
                $code = '['. date('Y-m-d H:i:s') .'][任务执行状态: FAIL]' . PHP_EOL;
                $code .= $json['info'] . PHP_EOL;
            }
            L($code, $name, '_task');
        });
        $i ++;
        if($i >= count($this->ids))$i = 0;
    }

}