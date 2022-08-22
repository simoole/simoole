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
        $params = [];
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
                        if($arr = self::analysisUri($request_uri, $list)){
                            $class_path = $arr[0];
                            $group_name = $route_conf['SUB_DOMAIN'][$sub_domain];
                            $route_path = $request_uri;
                            $params = $arr[1];
                        }
                    }
                }
            }
        }else{
            foreach($route_conf as $name => $list){
                if(in_array($name, ['COMMON','SUB_DOMAIN']))continue;
                $list = arrayMerge(self::$list, $list);
                if($arr = self::analysisUri($request_uri, $list)){
                    $class_path = $arr[0];
                    $group_name = $name;
                    $route_path = $request_uri;
                    $params = $arr[1];
                    break;
                }
            }
        }
        return [$class_path, $route_path, $group_name, $params];
    }

    /**
     * 解析URI
     * @param string $request_uri
     * @param array $list
     * @return array
     */
    static private function analysisUri(string $request_uri, array $list) : ?array
    {
        $params = [];
        $res = array_filter($list, function($k) use ($request_uri, &$params) {
            if($k == $request_uri)return true;
            elseif(strpos($k, '/^') === 0 && strpos($k, '$/') == (strlen($k) - 2) && preg_match($k, $request_uri, $arr, PREG_UNMATCHED_AS_NULL)){
                unset($arr[0]);
                if(!empty($arr))$params = array_values($arr);
                return true;
            }
            return false;
        }, ARRAY_FILTER_USE_KEY);
        if(empty($res))return null;
        $arr = array_values($res);
        return [$arr[0], $params];
    }
}
