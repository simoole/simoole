<?php

namespace Simoole;

class Conf
{
    static private $data = [];

    static public function __callstatic(string $name, array $arguments = [])
    {
        static $config = [];
        if(!isset($config[$name])){
            $config[$name] = [];
            if(is_file(CORE_PATH . 'ini/' . $name . INI_EXT))
                $config[$name] = Root::loadFiles(CORE_PATH . 'ini/' . $name . INI_EXT, true);
            if(is_file(__ROOT__ . 'config/system/' . $name . INI_EXT))
                $_config = Root::loadFiles(__ROOT__ . 'config/system/' . $name . INI_EXT, true);
            elseif($name == 'route'){
                $_config = Root::loadFiles(__ROOT__ . 'config/' . $name . INI_EXT, true);
                if(is_dir(__ROOT__ . 'config/' . $name)){
                    $files = glob(__ROOT__ . 'config/' . $name . '/*' . INI_EXT);
                    foreach($files as $file){
                        $arr = Root::loadFiles($file, true);
                        if(is_array($arr[array_key_first($arr)]))
                            $_config = arrayMerge($_config, Root::loadFiles($file, true));
                        else {
                            $filename = substr($file, strrpos($file, '/') + 1, strlen(INI_EXT) * -1);
                            $_config[$filename] = Root::loadFiles($file, true);
                        }
                    }
                }
            }elseif(empty($config[$name])){
                throw new \Exception('系统配置['. $name .'.ini.php]没有找到', 10101);
                return null;
            }
            if(!empty($_config)){
                $config[$name] = arrayMerge($config[$name], $_config);
            }
            //替换变量
            $config[$name] = changeConfigValue($config[$name]);
        }
        $conf = $config[$name];
        foreach($arguments as $key){
            if(isset($conf[$key])){
                $conf = $conf[$key];
            }else{
                return null;
            }
        }
        return $conf;
    }

    /**
     * 获取自定义配置
     * @param string ...$keys
     */
    static public function get(string ...$keys)
    {
        if(empty(self::$data)){
            $path = __ROOT__ . 'config/extend/';
            $files = scandir($path);
            foreach($files as $file){
                if(strpos($file, INI_EXT) > 0){
                    $config = Root::loadFiles(__ROOT__ . 'config/extend/' . $file, true);
                    self::$data = arrayMerge(self::$data, $config);
                }
            }
        }
        $data = self::$data;
        foreach($keys as $key){
            if(isset($data[$key])){
                $data = $data[$key];
            }else{
                return null;
            }
        }
        return $data;
    }

    /**
     * 设置自定义配置
     * @param string ...$keys
     */
    static public function set(string ...$keys)
    {
        if(empty(self::$data)){
            $path = __ROOT__ . 'config/extend/';
            $files = scandir($path);
            foreach($files as $file){
                if(strpos($file, INI_EXT) > 0){
                    $config = Root::loadFiles(__ROOT__ . 'config/extend/' . $file, true);
                    self::$data = arrayMerge(self::$data, $config);
                }
            }
        }
        $data = &self::$data;
        $val = array_pop($keys);
        foreach($keys as $key){
            if(isset($data[$key])){
                $data = $data[$key];
            }else{
                return false;
            }
        }
        $data = $val;
        return true;
    }
}
