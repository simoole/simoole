<?php
/**
 * 作为服务端的通信配置
 */
return [
    'host' => env('TCP_HOST'),
    'port' => env('TCP_PORT'),
    'is_binary' => env('TCP_IS_BIN'), //通信是否采用二进制(十六位)数据（通信含ajax和websocket）
    'is_encrypt' => env('TCP_IS_ENC'), //是否进行通信加密
    'encrypt_func' => '\Simoole\Util\Crypt::bin', //加密函数（参数：待加密串）
    'secret_key' => env('TCP_ENC_KEY') //加密秘钥
];
