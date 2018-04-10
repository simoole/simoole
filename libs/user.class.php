<?php
/**
 * 访客类
 * User: Dean.Lee
 * Date: 16/9/12
 */

namespace Root;

Class User
{
    //默认模块名称
    Public $mod_name = 'home';
    //默认控制器名称
    Public $cnt_name = 'index';
    //默认方法名称
    Public $act_name = 'index';

    //要输出的对象
    Public $response = null;

    Public $fd = null;
    Public $get = [];
    Public $post = [];
    Public $request = [];
    Public $server = [];
    Public $header = [];
    Public $cookie = [];
    Public $files = [];
    Public $input = '';

    //时间轨迹
    Private $starttime = 0;
    Private $runtime = 0;

    //数据库短连接
    Public $db_links = [];

    //sessionid
    Public $session = null;
    Private $sess_conf = null;
    //运行日志
    Private $running_time_log = '';

    Public function __construct(array $data, &$response = null)
    {
        if($response !== null)$this->response = &$response;
        $_GET = $this->get = isset($data['get'])?$data['get']:[];
        $_POST = $this->post = isset($data['post'])?$data['post']:[];
        $this->server = isset($data['server'])?$data['server']:[];
        $this->header = isset($data['header'])?$data['header']:[];
        $_SERVER = $this->server = array_merge($this->server, $this->header);
        $_COOKIE = $this->cookie = isset($data['cookie'])?$data['cookie']:[];
        $_FILES = $this->files = isset($data['files'])?$data['files']:[];
        $this->input = isset($data['input'])?$data['input']:[];
        $_REQUEST = $this->request = array_merge($_GET, $_POST);
        $this->fd = isset($data['fd'])?$data['fd']:null;

        $GLOBALS = [];
        $this->mod_name = $GLOBALS['MODULE_NAME'] = $data['mod_name'];
        $this->cnt_name = $GLOBALS['CONTROLLER_NAME'] = $data['cnt_name'];
        $this->act_name = $GLOBALS['ACTION_NAME'] = isset($data['act_name']) ? $data['act_name'] : null;

        $_SESSION = [];
        $this->sess_conf = \Root::$conf['SESSION'];
        if($this->sess_conf['AUTO_START']){
            $sessid = null;
            if(isset($_COOKIE['PHPSESSID']) && is_string($_COOKIE['PHPSESSID']) && strlen($_COOKIE['PHPSESSID']) == 40)$sessid = $_COOKIE['PHPSESSID'];
            $this->session = new Session($sessid);
            $_SESSION = $this->session->getData();
        }

        //记录开始访问日志
        $date = date('Y-m-d H:i:s');
        $this->starttime = $this->runtime = round(microtime(true) * 10000);
        $gtime = time() - strtotime(gmdate('Y-m-d H:i:s'));
        $gdate = [floor($gtime / 3600), floor(($gtime % 3600)/60)];
        $gdateStr = ($gdate[0] < 10 ? '0' . $gdate[0] : $gdate[0]) . ':' . ($gdate[1] < 10 ? '0' . $gdate[1] : $gdate[1]);
        if(!empty($this->act_name))
            $path = $this->mod_name . '/' . $this->cnt_name . '/' . $this->act_name . C('HTTP.ext') . PHP_EOL;
        else
            $path = $this->mod_name . '/' . $this->cnt_name . C('HTTP.ext') . PHP_EOL;
        $this->running_time_log = "\n[ {$date}+{$gdateStr} ] " . $this->server['remote_addr'] . ' /' . $path;
    }

    Public function __wakeup()
    {
        $_SESSION = $this->session->getData();

        //记录开始访问日志
        $date = date('Y-m-d H:i:s');
        $this->starttime = $this->runtime = round(microtime(true) * 10000);
        $gtime = time() - strtotime(gmdate('Y-m-d H:i:s'));
        $gdate = [floor($gtime / 3600), floor(($gtime % 3600)/60)];
        $gdateStr = ($gdate[0] < 10 ? '0' . $gdate[0] : $gdate[0]) . ':' . ($gdate[1] < 10 ? '0' . $gdate[1] : $gdate[1]);
        if(!empty($this->act_name))
            $path = $this->mod_name . '/' . $this->cnt_name . '/' . $this->act_name . C('HTTP.ext') . PHP_EOL;
        else
            $path = $this->mod_name . '/' . $this->cnt_name . C('HTTP.ext') . PHP_EOL;
        $this->running_time_log = "\n[ {$date}+{$gdateStr} ] " . $this->server['remote_addr'] . ' /' . $path;
    }

    /**
     * 记录运行日志
     */
    Public function log(string $msg){
        $time = round(microtime(true) * 10000);
        $msg .= ' [RunTime: '. ($time - $this->runtime)/10000 .'s]' . PHP_EOL;
        $this->running_time_log .= $msg;
        $this->runtime = $time;
    }

    /**
     * 保存会话数据
     */
    Public function save(){
        if(is_object($this->session)){
            if(is_object($this->response)){
                $this->response->cookie('PHPSESSID', $this->session->getId(), 0, $this->sess_conf['PATH'], $this->sess_conf['DOMAIN']?:'');
            }
        }
        $this->running_time_log .= 'INFO: --END-- [TotalRunningTime: '. (round(microtime(true)*10000) - $this->starttime)/10000 .'s]' . PHP_EOL;
        L($this->running_time_log, 'client', $this->mod_name);
    }

}