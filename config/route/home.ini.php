<?php
return [
    //路由配置支持正则表达式，必须由/^...$/包含，子匹配将作为方法的参数按顺序带入
    '/' => "\\App\\Controller\\IndexController@index"
];