<?php
/**
 * LOG日志输出模式
 */
return [
    //按多长时间来分割 i-分钟 h-小时 d-天 w-周 m-月 留空则不分割
    'split' => env('LOG_SPLIT'),
    //保留最近的7份日志,多余的自动删除,0则表示不删除
    'keep' => env('LOG_KEEP'),
    //异常日志输出形式, xml或json
    'errorfile' => env('LOG_FORMAT'),
    'errortype' => [
        E_ERROR,
        E_WARNING,
        E_PARSE,
        E_NOTICE,
        E_CORE_ERROR,
        E_CORE_WARNING,
        E_COMPILE_ERROR,
        E_COMPILE_WARNING,
        E_USER_ERROR,
        E_USER_WARNING,
        E_USER_NOTICE,
        E_STRICT,
        E_RECOVERABLE_ERROR
    ]
];