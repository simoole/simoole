<?php
/**
 * 核心控制类
 * User: Dean.Lee
 * Date: 16/9/12
 */

namespace Root;

Class Controller
{
    protected $vars = [];

    /**
     * 视图变量入库
     * @param mixed $data 变量数组或变量名
     * @param string $val 变量值
     */
    protected function assign($data, string $val = '')
    {
        if(is_array($data)){
            foreach($data as $key => $val){
                if(preg_match('/^[A-Za-z_]\w+$/', $key))
                    $this->vars[$key] = $val;
            }
        }elseif(preg_match('/^[A-Za-z_]\w+$/', $data)){
            $this->vars[$data] = $val;
        }
    }

    /**
     * 加载视图
     * @param string $path 视图路径(模块名/控制器名/方法名)
     */
    protected function display(string $path = '')
    {
        $mod_name = \Root::$user->mod_name;
        $cnt_name = \Root::$user->cnt_name;
        $act_name = \Root::$user->act_name;

        if(!empty($path)){
            $arr = explode('/', $path);
            if(count($arr) == 1){
                $act_name = $arr[0];
            }elseif(count($arr) == 2){
                $cnt_name = $arr[0];
                $act_name = $arr[1];
            }elseif(count($arr) == 3){
                $mod_name = $arr[0];
                $cnt_name = $arr[1];
                $act_name = $arr[2];
            }
        }

        $path = APP_PATH . $mod_name . '/view/' . $cnt_name . '/' . $act_name . TPL_EXT;
        \Root::$user->response->header('Content-Type', 'text/html');
        \Root::$user->response->header('charset', 'utf8');
        \Root::loadFiles($path, $this->vars);
    }

    /**
     * JSON数据输出
     * @param $data
     */
    protected function jsonReturn($data)
    {
        if(!is_array($data))$data = [$data];
        $data = array_change_value($data);
        \Root::$user->response->header('Content-Type', 'application/json');
        \Root::$user->response->header('charset', 'utf8');
        echo json_encode($data);
    }

    /**
     * 成功输出
     * @param $msg 输出内容
     * @param string|int $url 跳转地址或AJAX状态码
     * @param int $second 跳转倒计时
     */
    protected function success($msg, string $url = null, int $second = 5)
    {
        \Root::$user->response->header('charset', 'utf8');
        if($url === true || I('header.x-requested-with') == 'XMLHttpRequest'){
            $data = array_change_value(['status' => $url?:1, 'info' => $msg]);
            \Root::$user->response->header('Content-Type', 'application/json');
            echo json_encode($data);
        }else{
            \Root::$user->response->header('Content-Type', 'text/html');
            \Root::loadFiles(COMMON_PATH . 'tpl/success.tpl.php', [
                'url' => $url,
                'message' => $msg,
                'second' => $second
            ]);
        }
    }

    /**
     * 失败输出
     * @param $msg 输出内容
     * @param string|int $url 跳转地址或AJAX状态码
     * @param int $second 跳转倒计时
     */
    protected function error($msg, string $url = null, int $second = 5)
    {
        \Root::$user->response->header('charset', 'utf8');
        if($url === true || I('header.x-requested-with') == 'XMLHttpRequest'){
            $data = array_change_value(['status' => $url?:0, 'info' => $msg]);
            \Root::$user->response->header('Content-Type', 'application/json');
            echo json_encode($data);
        }else{
            \Root::$user->response->header('Content-Type', 'text/html');
            \Root::loadFiles(COMMON_PATH . 'tpl/error.tpl.php', [
                'url' => $url,
                'message' => $msg,
                'second' => $second
            ]);
        }
    }

    /**
     * 输出的头部
     * @param string|array $key
     * @param string $value
     */
    protected function header(string $key, string $value)
    {
        if(is_array($key)){
            foreach($key as $k => $v){
                \Root::$user->response->header($k, $v);
            }
        }else
            \Root::$user->response->header($key, $value);
    }

    /**
     * cookie设置
     * @param $key
     * @param string $value
     * @param int $expire
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httponly
     */
    protected function cookie(string $key, string $value = '', int $expire = 0 , string $path = '/', string $domain  = '', bool $secure = false , bool $httponly = false)
    {
        \Root::$user->response->cookie($key, $value, $expire, $path, $domain, $secure, $httponly);
    }

    public function _start()
    {
        return true;
    }

    public function _end()
    {
    }
}