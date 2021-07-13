<?php
/**
 * SESSION配置
 * 本session只是仿照了PHP原生的$_SESSION工作原理
 * 与PHP原生$_SESSION无任何关联
 */
return [
    'AUTO_START' => true,
    'DOMAIN' => '',
    'PATH' => '/',
    'EXPIRE' => 180 * 60, //session到期时间单位(秒)
    'CLEANUP' => 60, //session过期清理频率(秒)
    'DRIVE' => 'TABLE' //session驱动 TABLE-内存表、REDIS-redis驱动
];