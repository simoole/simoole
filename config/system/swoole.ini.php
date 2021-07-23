<?php
return [
    'worker_num' => swoole_cpu_num(),    //同时运行的进程数目(可配置CPU核数的1-4倍)
    'max_request' => 10000, //此参数表示worker进程在处理完n次请求后结束运行。manager会重新创建一个worker进程。此选项用来防止worker进程内存溢出。
    'max_coroutine' => 100000, //单个worker进程最多同时处理的协程数
    'daemonize' => 0, //是否开启守护进程
    'dispatch_mode' => 2, //通道分配模式
    'package_max_length' => 200 * 1024 * 1024, //最大上传大小
//        'document_root' => __ROOT__ . 'cdn/',
//        'enable_static_handler' => true,
//        'http_autoindex' => true,
//        'http_index_files' => ['index.html'],
//        'static_handler_locations' => ['/uploads']
];