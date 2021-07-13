<?php
/**
 * 应用实例配置
 */
return [
    //自动增加try..catch
    'auto_try' => false,
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
    'keyt' => 'sXODQpGzexIwo8gJqdEj94ZFPc2KNUC3kBaTmMSL07r6u15yYnHifVlWbtvhAR'
];