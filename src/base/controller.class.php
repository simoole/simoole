<?php
/**
 * 核心控制类
 * User: Dean.Lee
 * Date: 16/9/12
 */

namespace Simoole\Base;
use Simoole\Conf;

Class Controller
{
    //是否开启本次访问的二进制编码/解码
    public $is_binary = null;
    //是否开启本次访问的二进制加/解密（必须开启二进制编码）
    public $is_encrypt = null;
    //解密密钥
    public $_skey = null;

    /**
     * 加/解密方法
     */
    public function _crypt(array $data)
    {
        $method = Conf::tcp('encrypt_func');
        return $method($data, $this->_skey);
    }

    public function __construct()
    {
        if($this->is_binary === null)
            $this->is_binary = Conf::tcp('is_binary');
        if($this->is_encrypt === null)
            $this->is_encrypt = Conf::tcp('is_encrypt');
        if($this->_skey === null)
            $this->_skey = Conf::tcp('secret_key');
    }

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
        $this->header('Content-Type', 'application/json');
        $this->header('charset', 'utf-8');
        echo json_encode($data);
        return true;
    }

    /**
     * 成功输出
     * @param $msg 输出内容
     * @param int $code 状态码
     */
    public function success($data, int $code = 1)
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
    public function error($data, int $code = 0, int $trigger_error = null)
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
     * 输出头部header
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
     * 输出尾部header（http2专用）
     */
    protected function trailer(string $key, string $value)
    {
        if(is_array($key)){
            foreach($key as $k => $v){
                U('response')->trailer($k, $v);
            }
        }else
            U('response')->trailer($key, $value);
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
