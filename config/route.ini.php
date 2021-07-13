<?php
/**
 * 路由映射配置
 */
return [
    //路由分组
    'home' => [
        '/' => "\\App\\Controller\\IndexController@index"
    ],
    //根据子域名映射分组，子域名必须小写
    'SUB_DOMAIN' => [
//        'www' => 'home'
    ],
    //所有子域名公共路由
    'COMMON' => [
        '/favicon.ico' => null
    ]
];
