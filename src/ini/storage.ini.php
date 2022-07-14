<?php
/**
 * 仓库配置
 */
return [
    'ENABLE' => env('STORAGE_ENABLE'),
    'PREFIX' => env('STORAGE_PREFIX'), //仓库前缀（缓存文件前缀）
    'KEY_PREFIX' => 'key_', //hash名前缀
    'EXPIRE' => env('STORAGE_EXPIRE'), //默认到期时间单位(秒)
    'CLEANUP' => env('STORAGE_CLEANUP'), //多久清理一次过期缓存
    'DRIVE' => env('STORAGE_DRIVE') //缓存引擎 redis或local
];