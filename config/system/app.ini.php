<?php
/**
 * 应用实例配置
 */
return [
    //应用名称
    'name' => APP_NAME,
    //应用访问地址
    'url' => env('APP_URL'),
    //自动增加try..catch
    'auto_try' => env('APP_AUTO_TRY'),
    //实例start前执行的函数名(不可进行数据库操作)
    'before_start' => null,
    //实例start后执行的函数名(不可进行数据库操作)
    'after_start' => null,
    //工作进程start后执行的函数名
    'worker_start' => null,
    //实例stop后执行的函数名(不可进行数据库操作)
    'after_stop' => null,
    //时区
    'timezone' => 'Asia/Shanghai',
    //加密字典
    'keyt' => env('APP_KEY_DICT')
];