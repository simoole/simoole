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
     * @param mixed $val 变量值
     */
    protected function assign($data, $val = '')
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
     * @param bool $isRuturn 是否返回视图，而非输出
     */
    protected function display(string $path = '', bool $isRuturn = false)
    {
        $mod_name = U('mod_name');
        $cnt_name = U('cnt_name');
        $act_name = U('act_name');

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
        U('response')->header('Content-Type', 'text/html');
        U('response')->header('charset', 'utf-8');
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
        U('response')->header('Content-Type', 'application/json');
        U('response')->header('charset', 'utf-8');
        echo json_encode($data);
        return true;
    }

    /**
     * 成功输出
     * @param $msg 输出内容
     * @param string|int $url 跳转地址或AJAX状态码
     * @param int $second 跳转倒计时
     */
    protected function success($msg, string $url = null, int $second = 5)
    {
        U('response')->header('charset', 'utf-8');
        if($url === true || I('header.x-requested-with') == 'XMLHttpRequest'){
            $data = array_change_value(['status' => $url?:1, 'info' => $msg]);
            U('response')->header('Content-Type', 'application/json');
            echo json_encode($data);
        }else{
            U('response')->header('Content-Type', 'text/html');
            \Root::loadFiles(COMMON_PATH . 'tpl/success.tpl.php', [
                'url' => $url,
                'message' => $msg,
                'second' => $second
            ]);
        }
        return true;
    }

    /**
     * 失败输出
     * @param $msg 输出内容
     * @param string|int $url 跳转地址或AJAX状态码
     * @param int $second 跳转倒计时
     */
    protected function error($msg, string $url = null, int $second = 5)
    {
        U('response')->header('charset', 'utf-8');
        if($url === true || I('header.x-requested-with') == 'XMLHttpRequest'){
            $data = array_change_value(['status' => $url?:0, 'info' => $msg]);
            U('response')->header('Content-Type', 'application/json');
            echo json_encode($data);
        }else{
            U('response')->header('Content-Type', 'text/html');
            \Root::loadFiles(COMMON_PATH . 'tpl/error.tpl.php', [
                'url' => $url,
                'message' => $msg,
                'second' => $second
            ]);
        }
        return false;
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
                U('response')->header($k, $v);
            }
        }else
            U('response')->header($key, $value);
    }

    /**
     * 重定向
     * @param string $url 要被定向的URL
     */
    protected function redirect(string $url){
        $this->header('Location', $url);
        U('response')->status(302);
        return true;
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
        U('response')->cookie($key, $value, $expire, $path, $domain, $secure, $httponly);
    }

    public function _start()
    {
        return true;
    }

    public function _end()
    {
    }
}