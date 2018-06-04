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
        'ext' => '.html', //URI后缀
        'tpl_ext' => '.tpl.php', //视图文件后缀
        'module' => 'home', //默认模块
        'controller' => 'index', //默认控制器
        'action' => 'index' //默认方法
    ],
    'APP' => [
        'subdomain' => false, //是否启用子域名
        'subdomain_list' => [], //子域名映射模块
        'before_start' => null, //实例start前执行的函数名(不可进行数据库操作)
        'after_start' => null, //实例start后执行的函数名(不可进行数据库操作)
        'worker_start' => null, //工作进程start后执行的函数名
        'after_stop' => null //实例stop后执行的函数名(不可进行数据库操作)
    ],
    'CACHE' => [
        'DRIVE' => 'FILETMP', //缓存驱动 FILETMP-文件缓存、MEMCACHE-memcache缓存、MEMCACHED-memcached缓存、REDIS-redis缓存
        'PORT' => null, //网络地址端口
        'TIMEOUT' => 24 * 3600, //缓存的默认超时时间(s)
        'MAX_SIZE' => 2 * 1024, //单个文件的最大尺寸限制(kb)
        'PREFIX' => 'cache_'
    ],
    'FILETMP' => [
        'path' => TMP_PATH
    ],
    'MEMCACHE' => [
        'host' => null,
        'port' => '11211'
    ],
    'MEMCACHED' => [
        'host' => null,
        'port' => '11211',
        'user' => null,
        'pass' => null
    ],
    'REDIS' => [
        'host' => null,
        'port' => '6379',
        'pass' => null
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
    'SERVER' => [
        'ip' => '127.0.0.1',
        'port' => '9501',
        'reactor_num' => 2, //poll线程的数量(根据CPU核数配置)
        'worker_num' => 4,    //同时运行的进程数目(可配置CPU核数的1-4倍)
        'backlog' => 128,   //最大握手排队数量
        'max_request' => 1000, //此参数表示worker进程在处理完n次请求后结束运行。manager会重新创建一个worker进程。此选项用来防止worker进程内存溢出。
        'daemonize' => 1, //是否开启守护进程
        'dispatch_mode' => 2, //通道分配模式
        'enable_child' => 1 //是否开启内部定时器（定时器会默认清理过期的内存表数据）
    ],
    'WEBSOCKET' => [
        'is_enable' => 0, //是否开启websocket
        'max_connections' => 10, //最大连接数 2的10次方(1024)
        'heartbeat_idle_time' => 600, //一个连接如果600秒内未向服务器发送任何数据，此连接将被强制关闭
        'heartbeat_check_interval' => 60, //心跳检测频率,表示每60秒遍历一次
        'data_type' => 'string', //message接收到的数据类型 string|json
        'heartbeat' => 1 //心跳类型：1-自动PING(PONG)心跳 2-手动心跳 0-关闭心跳
    ],
    'MEMORY_TABLE' => [
        '__SESSION' => [ //表名
            '__total' => 10, //内存表总行数(2的指数), 2的10次方等于1024
            '__expire' => 3 * 60 * 60, //有效期3小时
            'timeout' => 'int(4)', //字段名
            'data' => 'string(10240)' //字段名 长度最大10KB
        ],
        '__PROCESS' => [
            '__total' => 10,
            '__expire' => 0, //有效期无限制
            'id' => 'int(1)',
            'type' => 'int(1)', //进程类型 0-系统 1-管理进程 2-工作进程 3-任务进程
            'pid' => 'int(2)', //进程编号
            'receive' => 'int(8)', //进程接收数据包数量
            'sendout' => 'int(8)', //进程发送数据包数量
            'memory_usage' => 'int(8)', //内存占用
            'memory_used' => 'int(8)' //内存实际使用
        ],
        '__LOCK' => [
            '__total' => 10,
            '__expire' => 60, //有效期60秒
            'type' => 'int(1)', //锁类型, 0-自旋锁 1-异步锁
            'timeout' => 'int(4)' //锁的失效时间，到时间会自动解锁
        ]
    ],
    //类库表类型 0-按目录文件自动加载 1-率先按顺序加载表中的文件 2-只按顺序加载表中的文件
    'MAP_TYPE' => 0,
    //类库加载表
    'MAPS' => [
    ]
];
