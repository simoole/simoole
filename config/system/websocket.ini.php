<?php
/**
 * websocket配置s
 */
return [
    'is_enable' => 0, //是否开启websocket
    'max_connections' => 10, //最大连接数 2的10次方(1024)
    'heartbeat_idle_time' => 600, //一个连接如果600秒内未向服务器发送任何数据，此连接将被强制关闭
    'heartbeat_check_interval' => 60, //心跳检测频率,表示每60秒遍历一次
    'data_type' => 'string', //message接收到的数据类型 string|json
    'heartbeat' => 1 //心跳类型：1-自动PING(PONG)心跳 2-手动心跳 0-关闭心跳
];