<?php
/**
 * REDIS客户端配置
 * "DEFAULT"键名为默认配置，可在不特指的情况下直接使用
 */
return [
    'DEFAULT' => [
        'HOST' => env('REDIS_HOST'), //redis连接IP
        'PORT' => env('REDIS_PORT'), //redis连接端口
        'AUTH' => env('REDIS_AUTH'), //redis连接秘钥
        'DB' => env('REDIS_DB'),
        'EXPIRE' => env('REDIS_EXPIRE'), //默认字段有效期
        'USE_COROUTINE' => env('REDIS_COROUTINE') //是否使用协程redis客户端
    ]
];
