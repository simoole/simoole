<?php

namespace Simoole;

class Route
{
    //路由列表
    static public $list = [];

    /**
     * 动态添加路由
     */
    static public function add(string $path, string $class)
    {
        self::$list[$path] = $class;
    }

    /**
     * 解析路由
     * @param string $host 访问域名
     * @return array 路由列表
     */
    static public function getPath(string $host, string $request_uri): array
    {
        static $route_conf = [];
        if(empty($route_conf)){
            $route_conf = Conf::route();
            //获取公共路由列表
            self::$list = array_merge(self::$list, $route_conf['COMMON']);
        }
        $class_path = $route_path = $group_name = '';
        if(!empty($route_conf['SUB_DOMAIN'])){
            //根据子域名查找子域名路由
            if(!empty($host)){
                if(preg_match('/^http\:/', $host)){
                    $arr = parse_url($host);
                    $host = $arr['host'];
                }
                if($pos = strpos($host, '.')){
                    $sub_domain = strtolower(substr($host, 0, $pos));
                    if(isset($route_conf['SUB_DOMAIN'][$sub_domain])){
                        $list = arrayMerge(self::$list, $route_conf[$route_conf['SUB_DOMAIN'][$sub_domain]]);
                        if(array_key_exists($request_uri, $list)){
                            $class_path = $list[$request_uri];
                            $group_name = $route_conf['SUB_DOMAIN'][$sub_domain];
                            $route_path = $request_uri;
                        }
                    }
                }
            }
        }else{
            foreach($route_conf as $name => $list){
                if(in_array($name, ['COMMON','SUB_DOMAIN']))continue;
                $list = arrayMerge(self::$list, $list);
                if(array_key_exists($request_uri, $list)){
                    $class_path = $list[$request_uri];
                    $group_name = $name;
                    $route_path = $request_uri;
                    break;
                }
            }
        }
        return [$class_path, $route_path, $group_name];
    }
}
