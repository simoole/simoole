#!/usr/bin/env php
<?php
/**
 * 框架启动文件
 * Author: Dean.Lee
 * Date: 2019-11-25
 */

// 检测环境
if(version_compare(PHP_VERSION,'7.2.0','<'))
    die('php version must be >= v7.2.0!' . PHP_EOL);
if(substr(swoole_version(),0,1) == 4 && version_compare(swoole_version(),'4.5.0','<'))
    die('swoole version must be >= v4.5.0!' . PHP_EOL);

require __DIR__ . '/core/root.php';

//自动加载composer
// require './vendor/autoload.php';

\Core\Root::run();
