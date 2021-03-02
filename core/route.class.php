<?php


namespace Core;


class Route
{
    //路由列表
    Static Public $list = [];

    /**
     * 动态添加路由
     */
    Static Public function add(string $path, string $class)
    {
        self::$list[$path] = $class;
    }

    /**
     * 解析路由
     * @param string $host 访问域名
     * @return array 路由列表
     */
    Static Public function getPath(string $host, string $request_uri): array
    {
        $route_conf = Conf::route();
        //获取公共路由列表
        self::$list = $route_conf['COMMON'];
        $class_path = $route_path = $group_name = '';

        if(!empty($route_conf['SUB_DOMAIN'])){
            //根据子域名查找子域名路由
            $sub_domain = null;
            if(!empty($host)){
                if(strpos($host, 'http://') === 0)$host = substr($host, 7);
                if(strpos($host, 'https://') === 0)$host = substr($host, 8);
                $host = substr($host, 0, strpos($host, '/'));
                $arr = explode('.', $host);
                if(count($arr) > 2){
                    array_pop($arr);
                    array_pop($arr);
                    $sub_domain = strtolower(join('.', $arr));
                    if(isset($route_conf['SUB_DOMAIN'][$sub_domain])){
                        self::$list = array_mer(self::$list, $route_conf[$route_conf['SUB_DOMAIN'][$sub_domain]]);
                        if(array_key_exists($request_uri, self::$list)){
                            $class_path = self::$list[$request_uri];
                            $group_name = $route_conf['SUB_DOMAIN'][$sub_domain];
                            $route_path = $request_uri;
                        }
                    }
                }
            }
        }else{
            foreach($route_conf as $name => $list){
                if(in_array($name, ['COMMON','SUB_DOMAIN']))continue;
                $list = array_mer(self::$list, $list);
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
