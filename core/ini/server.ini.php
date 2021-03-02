<?php
/**
 * 系统核心配置文件
 * User: Dean.Lee
 * Date: 16/9/12
 */

return [
    'APP' => [
        'before_start' => null, //实例start前执行的函数名(不可进行数据库操作)
        'after_start' => null, //实例start后执行的函数名(不可进行数据库操作)
        'worker_start' => null, //工作进程start后执行的函数名
        'after_stop' => null //实例stop后执行的函数名(不可进行数据库操作)
    ],
    'SESSION' => [
        'AUTO_START' => true,
        'DOMAIN' => '',
        'PATH' => '/',
        'EXPIRE' => 180 * 60, //session到期时间单位(秒)
        'CLEANUP' => 60, //session过期清理频率(秒)
        'DRIVE' => 'TABLE' //session驱动 TABLE-内存表、REDIS-redis驱动
    ],
    'LOG' => [
        'split' => 'd', //按多长时间来分割 i-分钟 h-小时 d-天 w-周 m-月 留空则不分割
        'keep' => 7, //保留最近的7份日志,多余的自动删除,0则表示不删除
        'errorfile' => 'xml', //异常日志输出形式, xml或json
        'errortype' => [E_ERROR,E_WARNING,E_PARSE,E_NOTICE,E_CORE_ERROR,E_CORE_WARNING,E_COMPILE_ERROR,E_COMPILE_WARNING,E_USER_ERROR,E_USER_WARNING,E_USER_NOTICE,E_STRICT,E_RECOVERABLE_ERROR]
    ],
    'TIMEZONE' => 'Asia/Shanghai',
    //加密字典
    'KEYT' => 'sXODQpGzexIwo8gJqdEj94ZFPc2KNUC3kBaTmMSL07r6u15yYnHifVlWbtvhAR',
    'TCP' => [
        'host' => '0.0.0.0',
        'port' => '9500',
        'is_binary' => false, //通信是否采用二进制(十六位)数据（通信含ajax和websocket）
        'is_encrypt' => false, //是否进行通信加密
        'encrypt_func' => '\Root\Util\Crypt::bin', //加密函数（参数：待加密串）
        'secret_key' => '3mwut6ciw3D0CW89' //加密秘钥
    ],
    'SWOOLE' => [
        'worker_num' => swoole_cpu_num(),    //同时运行的进程数目(可配置CPU核数的1-4倍)
        'max_request' => 1000, //此参数表示worker进程在处理完n次请求后结束运行。manager会重新创建一个worker进程。此选项用来防止worker进程内存溢出。
        'max_coroutine' => 1000, //单个worker进程最多同时处理的协程数
        'daemonize' => 1, //是否开启守护进程
        'pid_file' => TMP_PATH . 'server.pid',
        'log_file' => TMP_PATH . 'running.tmp',
        'dispatch_mode' => 2 //通道分配模式
    ],
    'WEBSOCKET' => [
        'is_enable' => 0, //是否开启websocket
        'max_connections' => 10, //最大连接数 2的10次方(1024)
        'heartbeat_idle_time' => 600, //一个连接如果600秒内未向服务器发送任何数据，此连接将被强制关闭
        'heartbeat_check_interval' => 60, //心跳检测频率,表示每60秒遍历一次
        'data_type' => 'string', //message接收到的数据类型 string|json
        'heartbeat' => 1 //心跳类型：1-自动PING(PONG)心跳 2-手动心跳 0-关闭心跳
    ]
];
