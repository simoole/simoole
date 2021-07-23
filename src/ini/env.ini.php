<?php
/**
 * 系统常量配置表
 */

//CLI命令
$comms = ['help', 'start', 'restart', 'update', 'stop', 'reload', 'cleanup'];
$cli_command = strpos($argv[0], 'simoole') !== false ? ($argv[1] ?? $comms[0]) : $argv[2];
if($pos = strpos($cli_command, ':')){
    define('CLI_COMMAND_VERSION', substr($cli_command, $pos + 1));
    $cli_command = substr($cli_command, 0, $pos);
}
if(!empty($cli_command) && in_array($cli_command, $comms))define('CLI_COMMAND', $cli_command);
else define('CLI_COMMAND', 'help');

define('SIMOOLE_VERSION', '4.0.0');
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
if(!is_dir(COMMON_PATH))@mkdir(COMMON_PATH);
//缓存目录路径
define('TMP_PATH', __ROOT__ . 'tmp/');
if(!is_dir(TMP_PATH))@mkdir(TMP_PATH);
//日志目录路径
define('LOG_PATH', __ROOT__ . 'log/');
if(!is_dir(LOG_PATH))@mkdir(LOG_PATH);
