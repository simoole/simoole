<?php

namespace Core;

class Conf
{
    static private $data = [];

    static public function __callStatic(string $name, array $arguments = [])
    {
        static $config = [];
        if(!isset($config[$name])){
            $config[$name] = [];
            if(in_array($name, ['server','mtable']))
                $config[$name] = Root::loadFiles(CORE_PATH . 'ini/' . $name . INI_EXT, true);
            if(in_array($name, ['server','database','map','mtable','process','redis']))
                $_config = Root::loadFiles(__ROOT__ . 'config/system/' . $name . INI_EXT, true);
            elseif($name == 'route')
                $_config = Root::loadFiles(__ROOT__ . 'config/' . $name . INI_EXT, true);
            else{
                trigger_error('系统配置['. $name .'.ini.php]没有找到');
                return null;
            }
            if(!empty($_config)){
                $config[$name] = array_mer($config[$name], $_config);
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
                    self::$data = array_mer(self::$data, $config);
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
                    self::$data = array_mer(self::$data, $config);
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
