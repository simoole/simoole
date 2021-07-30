<?php
/**
 * 数据库配置文件
 * "DEFAULT"键名为默认配置，可在不特指的情况下直接使用
 */
return [
    'DEFAULT' => [
        'TYPE'   => env('DB_TYPE'), // 数据库类型
        'HOST'   => env('DB_HOST'), // 服务器地址
        'NAME'   => env('DB_NAME'), // 数据库名
        'USER'   => env('DB_USER'), // 用户名
        'PASS'   => env('DB_PASS'), // 密码
        'PORT'   => env('DB_PORT'), // 端口
        'PREFIX' => env('DB_PREFIX'), // 数据库表前缀
        'CHARSET'=> env('DB_CHARSET'), // 字符集
        'POOL'   => 0  //连接池最大容量(0为禁用连接池，每次会话结束后连接将会被回收)，连接池中的连接只有在需要的时候才会创建，如果5分钟没有使用则会被回收
    ]
];
