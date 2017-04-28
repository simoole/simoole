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

    //sessionid
    Private $sessid = null;
    //session config
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
        $GLOBALS['MODULE_NAME'] = $data['mod_name'];
        $GLOBALS['CONTROLLER_NAME'] = $data['cnt_name'];
        $GLOBALS['ACTION_NAME'] = isset($data['act_name']) ? $data['act_name'] : null;
        $_SESSION = [];

        $this->sess_conf = \Root::$conf['SESSION'];
        if($this->sess_conf['AUTO_START']){
            $this->sessionStart();
        }

        //记录开始访问日志
        $date = date('Y-m-d H:i:s');
        $this->starttime = $this->runtime = round(microtime(true) * 10000);
        $gtime = time() - strtotime(gmdate('Y-m-d H:i:s'));
        $gdate = [floor($gtime / 3600), floor(($gtime % 3600)/60)];
        $gdateStr = ($gdate[0] < 10 ? '0' . $gdate[0] : $gdate[0]) . ':' . ($gdate[1] < 10 ? '0' . $gdate[1] : $gdate[1]);
        $this->running_time_log = "\n[ {$date}+{$gdateStr} ] " . $this->server['remote_addr'] . ' /' . $this->mod_name . '/' . $this->cnt_name . '/' . $this->act_name . C('APPS.ext') . PHP_EOL;
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
     * 保存$_SESSION中的数据
     */
    Public function sessionSave(){
        if(!empty($this->sessid)){
            if($this->response){
                $this->response->cookie('PHPSESSID', $this->sessid, $this->sess_conf['EXPIRE'] + time(), $this->sess_conf['PATH'], $this->sess_conf['DOMAIN']?:'');
                T('__SESSION')->set($this->sessid, [
                    'timeout' => $this->sess_conf['EXPIRE'] + time(),
                    'data' => $_SESSION
                ]);
            }else{
                T('__SESSION')->set($this->sessid, [
                    'data' => $_SESSION
                ]);
            }
            $_SESSION = [];
        }elseif(!empty($_SESSION)){
            trigger_error('没有开启SESSION无法使用$_SESSION!', E_USER_WARNING);
        }
        $this->running_time_log .= 'INFO: --END-- [TotalRunningTime: '. (round(microtime(true)*10000) - $this->starttime)/10000 .'s]' . PHP_EOL;
        L($this->running_time_log, 'client', $this->mod_name);
    }

    /**
     * 开启SESSION
     * @param string $sessid SESSION ID 用于断开重连或跨通道应用
     */
    Public function sessionStart(string $sess_id = null)
    {
        $sessid = $sess_id?:$this->sessid;
        $_SESSION = [];
        if (empty($sessid)) {
            if(array_key_exists('PHPSESSID', $this->cookie)){
                $sessid = $this->cookie['PHPSESSID'];
            }else
                $sessid = createCode(40, false);
        }
        //判断sessid是否存在
        $sessDatas = T('__SESSION')->get($sessid);
        if($sessDatas && isset($sessDatas['data']) && isset($sessDatas['timeout'])){
            //判断是否session过期
            if($sessDatas['timeout'] < time()){
                T('__SESSION')->del($sessid);
                $sessid = createCode(40, false);
            }else{
                $_SESSION = $sessDatas['data'];
            }
        }

        $this->sessid = $sessid;

        return $sessid;
    }

    /**
     * 返回sessionid
     */
    Public function sessionId(string $sess_id = null)
    {
        if(!empty($sess_id) && is_string($sess_id)){
            if(!empty($_SESSION))trigger_error('sessionId()必须在sessionStart()之前使用!');
            $this->sessid = $sess_id;
            return true;
        }else
            return $this->sessid;
    }

    /**
     * 检测sess_id是否有效
     * @param $sess_id
     */
    Public function sessionHas(string $sess_id){
        return T('__SESSION')->exist($sess_id);
    }
}