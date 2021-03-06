<?php
/**
 * 框架总纲类
 * User: Dean.Lee
 * Date: 16/9/12
 */
namespace Core;

//框架目录
defined('__ROOT__') or define('__ROOT__', realpath(__DIR__ . '/../') . '/');
defined('CORE_PATH') or define('CORE_PATH', __DIR__ . '/');
defined('__APP__') or define('__APP__', 'app');

include_once CORE_PATH . "ini/env.ini.php";

Class Root
{
    //swoole对象
    Static Public $serv = null;

    //进程对象
    Static Public $worker = null;

    //用户对象
    Static Public $user = [];

    //所有类库文件的地图
    Static Public $map = [];

    /**
     * 主框架运行
     */
    Static Public function run()
    {
        $command = "\\Core\\Root::" . CLI_COMMAND;
        $command();
    }

    /**
     * 启动框架
     */
    Static Private function start()
    {
        if(is_file(TMP_PATH . 'server.pid')){
            $pid = @file_get_contents(TMP_PATH . 'server.pid');
            if($pid && \Swoole\Process::kill($pid, 0))die("Framework has been started!" . PHP_EOL);
        }
        ini_set('default_socket_timeout', -1);
        //开启session
        @session_start();
        echo "Framework Starting...", PHP_EOL;
        //加载函数库
        self::loadFunc(CORE_PATH);
        //加载框架类库
        self::loadClass(CORE_PATH);
        //生成本实例的hash值
        define('HASH_KEY', createKey(time(), false, 'sXODQpGzexIwo8gJqdEj94ZFPc2KNUC3kBaTmMSL07r6u15yYnHifVlWbtvhAR'));
        //检测端口是否可用
        $conf = Conf::server('TCP');
        if(!checkPort($conf['host'], $conf['port'])){
            die('Port is occupied!' . PHP_EOL . "Starting Failed!" . PHP_EOL);
        }
        date_default_timezone_set(Conf::server('TIMEZONE'));

        @unlink(TMP_PATH . 'running.tmp');

        //启动异常处理和控制台
        Console::load();

        //创建内存表
        if(Table::create(Conf::mtable()))
            echo "Memory table creation finish!", PHP_EOL;
        else {
            echo "Memory table creation failed!", PHP_EOL, "Starting Failed!", PHP_EOL;
            return;
        }

        $setup = Conf::server('SWOOLE');
        if(Conf::server('WEBSOCKET', 'is_enable')){
            self::$serv = Websocket::create($conf['host'], $conf['port']);
            if(Conf::server('WEBSOCKET', 'heartbeat') == 1){
                $setup['heartbeat_idle_time'] = Conf::server('WEBSOCKET','heartbeat_idle_time');
                $setup['heartbeat_check_interval'] = Conf::server('WEBSOCKET','heartbeat_check_interval');
            }
        }else{
            self::$serv = new \Swoole\Http\Server($conf['host'], $conf['port']) or die('Swoole Starting Failed!' . PHP_EOL);
        }

        self::$serv->set($setup);

        self::$serv->on('start', 'Core\\Http::start');
        self::$serv->on('shutdown', 'Core\\Http::shutdown');
        self::$serv->on('managerstart', 'Core\\Http::managerStart');

        //设置工作/任务进程启动回调
        self::$serv->on('workerstart', 'Core\\Worker::onstart');

        //设置工作/任务进程结束回调
        self::$serv->on('workerstop', 'Core\\Worker::onstop');

        //设置进程间管道通信回调
        self::$serv->on('pipemessage', 'Core\\Worker::pipeMessage');

        //设置HTTP请求回调
        self::$serv->on('request', 'Core\\Http::request');

        //创建子进程
        Sub::create();

        //实例启动前执行
        $method = Conf::server('APP','before_start');
        if(!empty($method))$method();
        self::$serv->start();
    }

    /**
     * 结束框架
     */
    Static Public function stop()
    {
        $pid = @file_get_contents(TMP_PATH . 'server.pid');
        if($pid){
            if(\Swoole\Process::kill($pid, 0))\Swoole\Process::kill($pid, SIGTERM);
            else{
                foreach(glob(TMP_PATH . '*.pid') as $filename){
                    $pid = @file_get_contents($filename);
                    if(\Swoole\Process::kill($pid, 0))\Swoole\Process::kill($pid, 9);
                    @unlink($filename);
                }
            }
            die('Stop of Framework Success!' . PHP_EOL);
        }
        die('Framework not started!' . PHP_EOL);
    }

    /**
     * 重启框架
     */
    Static Public function restart()
    {
        $pid = @file_get_contents(TMP_PATH . 'server.pid');
        if($pid){
            if(\Swoole\Process::kill($pid, 0))\Swoole\Process::kill($pid, SIGTERM);
            foreach(glob(TMP_PATH . '*.pid') as $filename){
                $pid = @file_get_contents($filename);
                if(\Swoole\Process::kill($pid, 0))\Swoole\Process::kill($pid, 9);
                @unlink($filename);
            }
            echo('Stop of Framework Success!' . PHP_EOL);
        }else echo('Framework not started!' . PHP_EOL);
        sleep(1);
        self::start();
    }

    /**
     * 重载(热重启)框架
     */
    Static Public function reload()
    {
        $pid = @file_get_contents(TMP_PATH . 'server.pid');
        if($pid && \Swoole\Process::kill($pid, 0)){
            \Swoole\Process::kill($pid, SIGUSR1);
            $childs = glob(TMP_PATH . 'child_*.pid');
            foreach($childs as $child){
                if(strpos($child, 'child_0.pid') !== false)continue;
                $_pid = @file_get_contents($child);
                if(\Swoole\Process::kill($_pid, 0))\Swoole\Process::kill($_pid, SIGTERM);
            }
            die("Reload signal has been issued!" . PHP_EOL);
        }
        die("Framework not started!" . PHP_EOL);
    }

    /**
     * 加载文件
     * @param string $filepath 文件路径
     * @param boolean $return 是否获取返回值
     */
    Static Public function loadFiles(string $filepath, $return = false)
    {
        if(!is_file($filepath)){
            trigger_error($filepath . " does not exist!", E_USER_WARNING);
        }
        if($return === true)
            return require $filepath;
        elseif(is_array($return))
            extract($return);
        require $filepath;
    }

    /**
     * 加载指定目录中的所有类
     * @param string $dir
     */
    Static Public function loadClass(string $dir)
    {
        //加载框架类库
        $dir = rtrim($dir, '/');
        $files = scandir($dir);
        $files = array_diff($files, ['.', '..']);
        $dirs = [];
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $dirs[] = $path;
            } elseif (strpos($file, CLS_EXT) > 0) {
                foreach (self::$map as $m) {
                    if ($m['path'] == $path) continue 2;
                }
                self::loadFiles($path);
                foreach (get_declared_classes() as $classname) {
                    $classname = "\\" . str_replace("\\\\", "\\", $classname);
                    if (!isset(self::$map[$classname]) && !empty(trim($classname, '\\'))) {
                        $ref = new \ReflectionClass($classname);
                        if($ref->getFileName() == $path){
                            self::$map[$classname] = [
                                'path' => $ref->getFileName(),
                                'classname' => $ref->getShortName(),
                                'vars' => array_column(json_decode(json_encode($ref->getProperties()),true), 'name'),
                                'methods' => array_column(json_decode(json_encode($ref->getMethods()),true), 'name')
                            ];
                        }
                    }
                }
            }
        }
        foreach ($dirs as $dir) {
            self::loadClass($dir);
        }
    }

    /**
     * 加载所有应用类
     */
    Static Public function loadAppClass()
    {
        $map_type = Conf::map('TYPE');
        $map_list = Conf::map('LIST');
        //加载类库表
        if($map_type > 0){
            foreach($map_list as $path){
                $path = APP_PATH . $path;
                foreach(self::$map as $m){
                    if($m['path'] == $path)continue 2;
                }
                self::loadFiles($path);
                foreach(get_declared_classes() as $classname){
                    $classname = "\\" . str_replace("\\\\", "\\", $classname);
                    if (!isset(self::$map[$classname]) && !empty(trim($classname, '\\'))) {
                        $ref = new \ReflectionClass($classname);
                        if($ref->getFileName() == $path){
                            self::$map[$classname] = [
                                'path' => $ref->getFileName(),
                                'classname' => $ref->getShortName(),
                                'vars' => array_column(json_decode(json_encode($ref->getProperties()),true), 'name'),
                                'methods' => array_column(json_decode(json_encode($ref->getMethods()),true), 'name')
                            ];
                        }
                    }
                }
            }
        }

        if($map_type < 2){
            self::loadClass(APP_PATH);
        }
    }

    /**
     * 加载工具函数及类库
     */
    Static Public function loadFunc(string $dir)
    {
        $dir = rtrim($dir, '/');
        $files = scandir($dir);
        $files = array_diff($files, ['.', '..']);
        foreach($files as $file){
            $path = $dir . '/' . $file;
            if(is_dir($path)){
                self::loadFunc($path);
            }elseif(strpos($file, FUN_EXT) > 0){
                //加载函数库
                self::loadFiles($path);
            }
        }
    }
}

