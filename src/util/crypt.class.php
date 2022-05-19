<?php

namespace Simoole\Util;


class Crypt
{
    /**
     * 可逆加密函数
     * @param string $str 待加密的字符串
     * @param string|null $key 混淆字符串
     * @return bool|string 返回加密后的字符串
     */
    static public function encode(string $str, string $key = null)
    {
        $char = \Simoole\Conf::app('keyt');
        $chars = str_split($char);
        $str = str_replace(['/', '+', '='], ['0x2f', '0x2b', '0x3d'], base64_encode($str));
        $strs = str_split($str);
        $keys = str_split(substr(preg_replace('/0-9a-zA-Z/i', '$0', base64_encode(sha1($key?:str_rot13($char)))),0,16));
        $arr = [];
        foreach($strs as $i => $v){
            if(($v_num = strpos($char, $v)) === false)return false;
            $num = 0;
            foreach($keys as $k){
                $k_num = strpos($char, $k);
                $num += $k_num;
            }
            $v_num = $v_num << ($num % 6);
            $arr[] = $chars[floor($v_num / 62) + ($i%4) * 10];
            $arr[] = $chars[$v_num % 62];
        }
        return implode('', $arr);
    }

    /**
     * 可逆解密函数
     * @param string $str 待解密的字符串
     * @param string|null $key 混淆字符串
     * @return bool|string 返回解密后的字符串
     */
    static public function decode(string $str, string $key = null)
    {
        $char = \Simoole\Conf::app('keyt');
        $chars = str_split($char);
        $strs = str_split($str);
        $keys = str_split(substr(preg_replace('/0-9a-zA-Z/i', '$0', base64_encode(sha1($key?:str_rot13($char)))),0,16));
        $arr = [];
        $n = 0;
        foreach($strs as $i => $v){
            if(($v_num = strpos($char, $v)) === false)return false;
            $_i = floor($i / 2);
            $num = 0;
            foreach($keys as $k){
                $k_num = strpos($char, $k);
                $num += $k_num;
            }
            if($i % 2){
                $arr[] = $chars[($v_num + $n) >> ($num % 6)];
            }
            else{
                $n = ($v_num - ($_i%4) * 10) * 62;
            }
        }
        $str = str_replace(['0x2f', '0x2b', '0x3d'], ['/', '+', '='], implode('', $arr));
        return base64_decode($str);
    }

    /**
     * 对aseii码数组进行二进制加密/解密
     * @param array $arr 要加密/解密的aseii数组
     * @param string $key 加密密钥
     * @return array
     */
    static public function bin(array $arr, string $key = null)
    {
        $key = $key ?: \Simoole\Conf::tcp('secret_key');
        if(empty($key)){
            throw new \Exception('加密失败，缺少密钥');
        }
        $k = 0;
        $bit = ceil(strlen(decbin($arr[0]))/8) * 8;
        $keys = [];
        $str = '';
        foreach(str_split($key) as $v){
            $str .= sprintf("%08d", decbin(ord($v)));
            if(strlen($str) == $bit){
                $keys[] = bindec($str);
                $str = '';
            }
        }
        foreach($arr as &$v){
            $v = $v ^ $keys[$k ++];
            if($k == count($keys))$k = 0;
        }
        return $arr;
    }
}
