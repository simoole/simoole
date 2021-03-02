<?php
/**
 * 核心控制类
 * User: Dean.Lee
 * Date: 16/9/12
 */

namespace Core\Base;
use Core\Conf;

Class Controller
{
    /**
     * JSON数据输出
     * @param $data 要输出的数据
     * @return bool
     */
    protected function jsonReturn($data)
    {
        if(!is_array($data))$data = [$data];
        $data = array_change_value($data);
        //是否启用二进制通信
        if(Conf::server('TCP','is_binary')){
            $this->header('Content-Type', 'application/octet-stream');
            $this->header('Content-Transfer-Encoding', 'binary');
            $arr = encodeASCII(json_encode($data));
            if(Conf::server('TCP','is_encrypt')){
                $method = C('TCP.encrypt_func');
                $arr = $method($arr);
            }
            array_unshift($arr, 'C*');
            $this->write(call_user_func_array('pack', $arr));
        }else{
            $this->header('Content-Type', 'application/json');
            $this->header('charset', 'utf-8');
            echo json_encode($data);
        }
        return true;
    }

    /**
     * 成功输出
     * @param $msg 输出内容
     * @param int $code 状态码
     */
    protected function success($data, int $code = 1)
    {
        return $this->jsonReturn([
            'status' => $code,
            'data' => $data
        ]);
    }

    /**
     * 失败输出
     * @param $data 输出内容
     * @param int $code 跳转地址或AJAX状态码
     * @param int $trigger_error ERROR错误码
     */
    protected function error($data, int $code = 0, int $trigger_error = null)
    {
        if($trigger_error !== null){
            if(is_array($data) || is_object($data))$data = json_encode($data);
            trigger_error($data, $trigger_error);
        }
        $this->jsonReturn([
            'status' => $code,
            'data' => $data
        ]);
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
