<?php
/**
 * 仓库配置
 */
return [
    'PREFIX' => 'storage_', //仓库前缀（缓存文件前缀）
    'KEY_PREFIX' => 'key_', //hash名前缀
    'EXPIRE' => 24 * 3600, //默认到期时间单位(秒)
    'CLEANUP' => 60, //多久清理一次过期缓存
    'DRIVE' => 'redis' //缓存引擎 redis或file
];