<?php
/**
 * 作为服务端的通信配置
 */
return [
    'host' => '0.0.0.0',
    'port' => '9200',
    'is_binary' => false, //通信是否采用二进制(十六位)数据（通信含ajax和websocket）
    'is_encrypt' => false, //是否进行通信加密
    'encrypt_func' => '\Simoole\Util\Crypt::bin', //加密函数（参数：待加密串）
    'secret_key' => '3mwut6ciw3D0CW89' //加密秘钥
];