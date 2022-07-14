<?php
/**
 * 框架总纲类
 * User: Dean.Lee
 * Date: 16/9/12
 */
namespace Simoole;

//框架目录
defined('__ROOT__') or define('__ROOT__', realpath(__DIR__ . '/../') . '/');
defined('CORE_PATH') or define('CORE_PATH', __DIR__ . '/');
defined('__APP__') or define('__APP__', 'app');

include_once CORE_PATH . "ini/env.ini.php";

Class Root
{
    //swoole对象
    static public $serv = null;

    //进程对象
    static public $worker = null;

    //会话对象
    static public $user = [];

    //所有类库文件的地图
    static public $map = [];

    /**
     * 主框架运行
     */
    static public function run()
    {
        $command = "\\Simoole\\Root::" . CLI_COMMAND;
        $command();
    }

    /**
     * 命令列表
     */
    static public function help()
    {
        echo <<<HELP
  命令列表：
    1. start - 启动框架
    2. reload - 热更新（只会启动工作进程和自定义子进程，并重载app文件夹中的类库）
    3. restart - 重启框架
    4. stop - 结束框架
    5. cleanup - 清空table内存表
    6. update - 更新框架版本
    7. console - 进入框架控制台

HELP;
    }

    /**
     * 启动框架
     */
    static Private function start()
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
        $conf = Conf::tcp();
        if(!checkPort($conf['host'], $conf['port'])){
            die('Port is occupied!' . PHP_EOL . "Starting Failed!" . PHP_EOL);
        }
        date_default_timezone_set(Conf::app('timezone'));

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

        $setup = Conf::swoole();
        if(Conf::websocket('is_enable')){
            self::$serv = Websocket::create($conf['host'], $conf['port']);
            if(Conf::websocket('heartbeat') == 1){
                $setup['heartbeat_idle_time'] = Conf::websocket('heartbeat_idle_time');
                $setup['heartbeat_check_interval'] = Conf::websocket('heartbeat_check_interval');
            }
        }else{
            self::$serv = new \Swoole\Http\Server($conf['host'], $conf['port']) or die('Swoole Starting Failed!' . PHP_EOL);
        }

        self::$serv->set($setup);

        self::$serv->on('start', 'Simoole\\Http::start');
        self::$serv->on('shutdown', 'Simoole\\Http::shutdown');
        self::$serv->on('managerstart', 'Simoole\\Http::managerStart');

        //设置工作/任务进程启动回调
        self::$serv->on('workerstart', 'Simoole\\Worker::onstart');

        //设置工作/任务进程结束回调
        self::$serv->on('workerstop', 'Simoole\\Worker::onstop');

        //设置进程间管道通信回调
        self::$serv->on('pipemessage', 'Simoole\\Worker::pipeMessage');

        //设置HTTP请求回调
        self::$serv->on('request', 'Simoole\\Http::request');

        //创建子进程
        Sub::create();

        //实例启动前执行
        $method = Conf::app('before_start');
        if(!empty($method))$method();
        self::$serv->start();
    }

    /**
     * 结束框架
     */
    static public function stop()
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
    static public function restart()
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
    static public function reload()
    {
        $pid = @file_get_contents(TMP_PATH . 'server.pid');
        if($pid && \Swoole\Process::kill($pid, 0)){
            \Swoole\Process::kill($pid, SIGUSR1);
            $childs = glob(TMP_PATH . 'child_*.pid');
            foreach($childs as $child){
                if(str_contains($child, 'child_0.pid'))continue;
                $_pid = @file_get_contents($child);
                if(\Swoole\Process::kill($_pid, 0))\Swoole\Process::kill($_pid, SIGTERM);
            }
            die("Reload signal has been issued!" . PHP_EOL);
        }
        die("Framework not started!" . PHP_EOL);
    }

    /**
     * 清空table表
     */
    static public function cleanup()
    {
        $pid = @file_get_contents(TMP_PATH . 'child_0.pid');
        if($pid && \Swoole\Process::kill($pid, 0)){
            \Swoole\Process::kill($pid, SIGUSR2);
            die("Cleanup signal has been issued!" . PHP_EOL);
        }
        die("Framework not started!" . PHP_EOL);
    }

    /**
     * 更新框架
     */
    static public function update()
    {
        \Swoole\Coroutine\run(function(){
            $hash_fun = function(string $path = CORE_PATH) use (&$hash_fun){
                $hash_arr = [];
                foreach(scandir($path) as $file){
                    if(in_array($file, ['.', '..']))continue;
                    $filepath = $path . $file;
                    if(is_dir($filepath)){
                        $hash_arr[$file] = $hash_fun($filepath . '/');
                    }else{
                        $hash_arr[$file] = hash_file('md5', $filepath);
                    }
                }
                return $hash_arr;
            };
            $cli = new \Swoole\Coroutine\Http\Client('code.simoole.com', '9988');
            $cli->post('/', [
                'files' => $hash_fun(),
                'current' => SIMOOLE_VERSION,
                'target' => CLI_COMMAND_VERSION
            ]);
            $res = json_decode($cli->body, true);
            $cli->close();

            if(json_last_error() === JSON_ERROR_NONE && !empty($res)){
                if($res['status'] == 1){
                    foreach($res['data'] as $path => $file){
                        $filepath = CORE_PATH . $path;
                        if(empty($file) && is_file($filepath)){
                            unlink($filepath);
                            echo $filepath . ' is deleted!' . PHP_EOL;
                        }elseif(!empty($file)){
                            if(is_file($filepath)){
                                file_put_contents($filepath, $file);
                                echo $filepath . " is updated!" . PHP_EOL;
                            }else{
                                $dir = CORE_PATH . strrchr($path, '/');
                                if(!is_dir($dir))mkdir($dir, 0777, true);
                                file_put_contents($filepath, $file);
                                echo $filepath . " is added!" . PHP_EOL;
                            }
                        }
                    }
                    echo 'Update Success! Restart to take effect.' . PHP_EOL;
                }else{
                    echo 'Update Failed! Failure Cause: ' . $res['data'] . PHP_EOL;
                }
            }else{
                echo 'Update Failed!' . PHP_EOL;
            }
        });
    }

    static public function console()
    {
        echo 'Please enter a command:' . PHP_EOL;
        echo ' - Show [workers|process|mtables]' . PHP_EOL;
        echo ' - Select [worker|process|mtable] [number|tablename]' . PHP_EOL;
        self::loadFiles(CORE_PATH . 'conf.class.php');
        self::loadFiles(CORE_PATH . 'console.class.php');
        self::loadFiles(CORE_PATH . 'root.fun.php');
        \Swoole\Coroutine::set(['hook_flags' => SWOOLE_HOOK_FILE]);
        \Swoole\Coroutine\run(function(){
            $console = new Console();
            if(!$console->connect()) return;
            $comstr = '';
            while(true){
                $pre = '<'.APP_NAME.'>';
                if(empty($comstr))
                    echo $pre . ' Console# ';
                else
                    echo $pre . ' Console:'. $comstr .'# ';
                $result = trim(fgets(STDIN));
                if(empty($result))continue;
                if(in_array($result, ['quit', 'exit']))break;
                $res = $console->handle($result, $comstr);
                if(!$res)break;
            }
            fclose(STDIN);
        });
        echo 'Exit!' . PHP_EOL;
    }

    /**
     * 将业务代码打包成二进制文件
     * @return void
     */
    static public function build()
    {
        define('BUILD_PATH', __ROOT__ .'build/');
        self::loadFiles(CORE_PATH . 'root' . FUN_EXT);
        $code_key = env('APP_CODE_KEY');
        if(!empty($code_key)) self::loadFiles(CORE_PATH . 'util/crypt' . CLS_EXT);
        //删除build目录
        if(is_dir(BUILD_PATH))delDir(BUILD_PATH);
        $build = function(string $path = APP_PATH) use (&$build, $code_key){
            $funs = $classes = $others = [];
            foreach(scandir($path) as $file){
                if(in_array($file, ['.', '..']))continue;
                $filepath = $path . $file;
                if(is_dir($filepath)){
                    $arr = $build($filepath . '/');
                    $funs = array_merge($funs, $arr[0]);
                    $classes = array_merge($classes, $arr[1]);
                    $others = array_merge($others, $arr[2]);
                }else{
                    $code = php_strip_whitespace($filepath);
                    $code = str_replace("\r\n", '', $code); //清除换行符
                    $code = str_replace("\n", '', $code); //清除换行符
                    $code = str_replace("\t", '', $code); //清除制表符
                    $pattern = ["/> *([^ ]*) *</","/[\s]+/","/<!--[^!]*-->/","/\" /","/ \"/","'/\*[^*]*\*/'"];
                    $replace = [">\\1<"," ","","\"","\"",""];
                    $code = preg_replace($pattern, $replace, $code);
                    $arr = encodeASCII($code);
                    if(!empty($code_key)){
                        $arr = Util\Crypt::bin($arr, $code_key);
                    }
                    array_unshift($arr, 'C*');
                    $code = call_user_func_array('pack', $arr);
                    if(str_contains($file, FUN_EXT))$funs[$filepath] = $code;
                    elseif(str_contains($file, CLS_EXT))$classes[$filepath] = $code;
                    else $others[$filepath] = $code;
                    echo "[{$filepath}] Read successfully." . PHP_EOL;
                }
            }
            return [$funs, $classes, $others];
        };
        [$funs, $classes, $others] = $build();
        mkdir(BUILD_PATH);
        foreach ($funs as $path => $code) {
            file_put_contents(BUILD_PATH . 'fun_' . md5($path), $code);
            echo "[{$path}] Packaging completed." . PHP_EOL;
        }
        foreach ($classes as $path => $code) {
            file_put_contents(BUILD_PATH . 'cls_' . md5($path), $code);
            echo "[{$path}] Packaging completed." . PHP_EOL;
        }
        foreach ($others as $path => $code) {
            file_put_contents(BUILD_PATH . 'otr_' . md5($path), $code);
            echo "[{$path}] Packaging completed." . PHP_EOL;
        }
        echo "Successfully! Building all completed." . PHP_EOL;
    }

    /**
     * 加载文件
     * @param string $filepath 文件路径
     * @param boolean $return 是否获取返回值
     */
    static public function loadFiles(string $filepath, $return = false)
    {
        if(!is_file($filepath)){
            throw new \Exception($filepath . " does not exist!", 10111);
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
    static public function loadClass(string $dir)
    {
        //加载框架类库
        $dir = rtrim($dir, '/');
        $files = scandir($dir);
        $files = array_diff($files, ['.', '..']);
        $code_key = getenv('APP_CODE_KEY');
        $dirs = [];
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $dirs[] = $path;
            } elseif (str_contains($file, CLS_EXT) || str_contains($file, 'cls_')) {
                foreach (self::$map as $m) {
                    if ($m['path'] == $path || 'cls_' . md5($m['path']) == $file) continue 2;
                }
                if (str_contains($file, CLS_EXT)) {
                    self::loadFiles($path);
                } elseif(str_contains($file, 'cls_')) {
                    if(!empty($code_key)){
                        $arr = unpack('C*', file_get_contents($path));
                        if(count($arr) > 0){
                            $arr = Util\Crypt::bin($arr, $code_key);
                            eval('?>' . decodeASCII($arr));
                        }
                    }else self::loadFiles($path);
                }

                foreach (get_declared_classes() as $classname) {
                    $classname = "\\" . str_replace("\\\\", "\\", $classname);
                    if (!isset(self::$map[$classname]) && !empty(trim($classname, '\\'))) {
                        $ref = new \ReflectionClass($classname);
                        if($ref->getFileName() == $path || str_contains($ref->getFileName(), md5($path))){
                            self::$map[$classname] = [
                                'path' => $path,
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
    static public function loadAppClass()
    {
        $map_type = Conf::map('TYPE');
        $map_list = Conf::map('LIST');
        $code_key = env('APP_CODE_KEY');
        //加载类库表
        if($map_type > 0){
            $_files = array_values(array_filter(scandir(APP_PATH), function($val){
                return str_contains($val, 'cls_');
            }));
            foreach($map_list as $path){
                $path = APP_PATH . $path;
                foreach(self::$map as $m){
                    if($m['path'] == $path)continue 2;
                }
                if(!empty($_files) && ($index = array_search('cls_' . md5($path), $_files)) !== false) {
                    if(!empty($code_key)){
                        $arr = unpack('C*', file_get_contents($path));
                        if(count($arr) > 0){
                            //解码二进制
                            $arr = Util\Crypt::bin($arr, $code_key);
                            eval('?>' . decodeASCII($arr));
                        }
                    }else self::loadFiles(APP_PATH . $_files[$index]);
                }else self::loadFiles($path);
                foreach(get_declared_classes() as $classname){
                    $classname = "\\" . str_replace("\\\\", "\\", $classname);
                    if (!isset(self::$map[$classname]) && !empty(trim($classname, '\\'))) {
                        $ref = new \ReflectionClass($classname);
                        if($ref->getFileName() == $path || str_contains($ref->getFileName(), md5($path))){
                            self::$map[$classname] = [
                                'path' => $path,
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
    static public function loadFunc(string $dir)
    {
        $dir = rtrim($dir, '/');
        $files = scandir($dir);
        $files = array_diff($files, ['.', '..']);
        $code_key = getenv('APP_CODE_KEY');
        foreach($files as $file){
            $path = $dir . '/' . $file;
            if(is_dir($path)){
                self::loadFunc($path);
            }elseif(str_contains($file, FUN_EXT)){
                //加载函数库
                self::loadFiles($path);
            }elseif(str_contains($file, 'fun_')){
                if(!empty($code_key)){
                    $arr = unpack('C*', file_get_contents($path));
                    if(count($arr) > 0){
                        //解码二进制
                        $arr = Util\Crypt::bin($arr, $code_key);
                        eval('?>' . decodeASCII($arr));
                    }
                }else self::loadFiles($path);
            }
        }
    }
}

