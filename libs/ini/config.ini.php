<?php
/**
 * 系统核心配置文件
 * User: Dean.Lee
 * Date: 16/9/12
 */

return [
    'HTTP' => [
        'cache_time' => 5, //请求结果缓存时间 5秒, 0代表不缓存
        'gzip' => 1, //响应数据压缩率, 0不压缩, 取值1-9压缩率越高CPU消耗越大
        'http2' => false, //启用HTTP2协议解析，需要依赖--enable-http2编译选项
    ],
    'APPS' => [
        'ext' => '.html', //URI后缀
        'tpl_ext' => '.tpl.php', //视图文件后缀
        'module' => 'home', //默认模块
        'controller' => 'index', //默认控制器
        'action' => 'index' //默认方法
    ],
    'CACHE' => [
        'DRIVE' => 'FILETMP', //缓存驱动 FILETMP-文件缓存、MEMCACHE-memcache缓存(待接入)、REDIS-redis缓存(待接入)
        'ADDRESS' => TMP_PATH, //缓存地址（网络地址）
        'PORT' => null, //网络地址端口
        'TIMEOUT' => 24 * 3600, //缓存的默认超时时间(s)
        'MAX_SIZE' => 2 * 1024, //单个文件的最大尺寸限制(kb)
        'PREFIX' => 'cache_'
    ],
    'SESSION' => [
        'AUTO_START' => true,
        'DOMAIN' => '',
        'PATH' => '/',
        'EXPIRE' => 180 * 60, //session到期时间单位(秒)
        'CLEANUP' => 60 //session过期清理频率(秒)
    ],
    'LOG' => [
        'split' => 'd', //按多长时间来分割 i-分钟 h-小时 d-天 w-周 m-月 留空则不分割
        'keep' => 7, //保留最近的7份日志,多余的自动删除,0则表示不删除
        'errortype' => 'xml' //异常日志输出形式, xml或json
    ],
    'TIMEZONE' => 'Asia/Shanghai',
    'SERVER' => [
        'ip' => '127.0.0.1',
        'port' => '9501',
        'reactor_num' => 2, //poll线程的数量(根据CPU核数配置)
        'worker_num' => 4,    //同时运行的进程数目(可配置CPU核数的1-4倍)
        'backlog' => 128,   //最大握手排队数量
        'max_request' => 1000, //此参数表示worker进程在处理完n次请求后结束运行。manager会重新创建一个worker进程。此选项用来防止worker进程内存溢出。
        'daemonize' => 1 //是否开启守护进程
    ],
    'WEBSOCKET' => [
        'is_enable' => 0, //是否开启websocket
        'heartbeat_idle_time' => 600, //一个连接如果600秒内未向服务器发送任何数据，此连接将被强制关闭
        'heartbeat_check_interval' => 60, //表示每60秒遍历一次
        'data_type' => 'string', //message接收到的数据类型 string|json
    ],
    'MEMORY_TABLE' => [
        '__SESSION' => [ //表名
            '__total' => 10, //内存表总行数(2的指数), 2的10次方等于1024
            'timeout' => 'int(4)', //字段名
            'data' => 'string(10240)' //字段名 长度最大100KB
        ],
        '__PROCESS' => [
            '__total' => 10,
            'id' => 'int(1)',
            'type' => 'int(1)', //进程类型 0-系统 1-管理进程 2-工作进程 3-任务进程
            'pid' => 'int(2)', //进程编号
            'receive' => 'int(4)', //进程接收数据包数量
            'sendout' => 'int(4)', //进程发送数据包数量
            'memory_usage' => 'int(4)', //内存占用
            'memory_used' => 'int(4)' //内存实际使用
        ]
    ]
];