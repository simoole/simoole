<?php
/**
 * 系统常量配置表
 */

//CLI命令
if(isset($argv[1]) && in_array($argv[1], ['start', 'restart', 'status', 'stop', 'reload']))define('CLI_COMMAND', $argv[1]);
else define('CLI_COMMAND', 'start');
//配置文件后缀
define('INI_EXT', '.ini.php');
//类库文件后缀
define('CLS_EXT', '.class.php');
//函数文件后缀
define('FUN_EXT', '.fun.php');

//队列常量
define('MEMORY_QUEUE_PUSH', 1); //内存队列写
define('MEMORY_QUEUE_POP', 2); //内存队列读
define('MEMORY_QUEUE_CLEAR', 0); //内存队列清空
define('MEMORY_QUEUE_LIST', 3); //内存队列列表
define('MEMORY_QUEUE_COUNT', 4); //内存队列数量

define('MEMORY_TABLE_SET', 5); //内存表设置（用于子进程定时清理内存表）

define('MEMORY_WEBSOCKET_GET', 6); //获取websocket连接数据
define('MEMORY_WEBSOCKET_SET', 7); //记录websocket连接数据
define('MEMORY_WEBSOCKET_DEL', 8); //删除websocket连接数据
define('MEMORY_WEBSOCKET_HEART', 9); //获取指定websocket连接数据，心跳专用

//应用目录
define('APP_PATH', __ROOT__ . __APP__ .'/');
//应用公共文件目录
define('COMMON_PATH', APP_PATH . 'common/');
//缓存目录路径
define('TMP_PATH', __ROOT__ . 'tmp/');
//日志目录路径
define('LOG_PATH', __ROOT__ . 'log/');
