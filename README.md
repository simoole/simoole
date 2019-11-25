# Simple-Swoole-Framework
基于swoole引擎的多进程&协程&常驻内存式PHP框架，结构清晰，部署简单，使用方便。可以灵活应对HTTP/Websocket服务，另有内置全局定时器、异步任务等。

-----------
## 简单部署
<pre><code>git clone git@github.com:ljj7410/Simple-Swoole-Framework.git</code></pre>
<pre><code>php index.php start //开启应用，第一次开启将会自动生成[apps]应用目录
php index.php stop //关闭应用
php index.php reload //热更新
php index.php restart //重启
php index.php status //查看进程状态</code></pre>

----------
## 应用目录结构
<pre>
|- apps
|- |- common  --公共目录
|- |- |- config  --配置目录
|- |- |- |- config.ini.php  --配置文件
|- |- |- |- database.ini.php  --数据库配置文件
|- |- |- tpl  --公共模板目录
|- |- |- |- error.tpl.php  --错误提示模板文件
|- |- |- |- success.tpl.php  --成功提示模板文件
|- |- |- util  --第三方类库目录
|- |- home  --默认模块目录
|- |- |- controller  --控制器目录
|- |- |- |- index.class.php  --默认控制器文件
|- |- |- model  --模块目录
|- |- |- view  --视图目录
|- |- |- |- index  --默认视图目录
|- |- |- |- |- index.tpl.php  --默认视图模板文件
|- |- |- websocket  --websocket目录
|- |- |- |- index.class.php  --默认websocket文件
|- |- runtime  --运行时产生的文件目录
|- |- |- log  --日志目录
|- |- |- tmp  --临时文件目录
</pre>

----------
### 作者
李俊杰（Dean.Lee），毕业于解放军信息工程大学
> 邮箱：dean7410@163.com

### 声明
SSF由作者独立研发，版权归属个人，与任何组织无关。未经作者授权，谢绝任何人或组织借用SSF进行商业行为。

----------
## 配置说明
SSF提供了灵活的全局配置功能，采用最有效率的PHP返回数组方式定义，支持惯例配置、公共配置、模块配置、调试配置和动态配置。

> 对于有些简单的应用，你无需配置任何配置文件，而对于复杂的要求，你还可以增加动态配置文件。

> 系统的配置参数是通过C()函数进行全局存取的，存取方式简单高效。

***
### 1. 常规配置
```php
return [
    'HTTP' => [
        'cache_time' => 5, //请求结果缓存时间 5秒, 0代表不缓存
        'gzip' => 1, //响应数据压缩率, 0不压缩, 取值1-9压缩率越高CPU消耗越大
        'http2' => false, //启用HTTP2协议解析，需要依赖--enable-http2编译选项
        'ext' => '.html', //URI后缀
        'tpl_ext' => '.tpl.php', //视图文件后缀
        'module' => 'home', //默认模块
        'controller' => 'index', //默认控制器
        'action' => 'index' //默认方法
    ],
    'APP' => [
        'subdomain' => false, //是否启用子域名
        'subdomain_list' => [], //子域名映射模块，如['www' => 'home']
        'before_start' => null, //实例start前执行的函数名(不可进行数据库操作)
        'after_start' => null, //实例start后执行的函数名(不可进行数据库操作)
        'worker_start' => null, //工作进程start后执行的函数名
        'after_stop' => null //实例stop后执行的函数名(不可进行数据库操作)
    ],
    'SERVER' => [
        'ip' => '0.0.0.0', //实例绑定的IP，0.0.0.0为通用绑定
        'port' => '9501', //实例绑定端口
        'reactor_num' => 2, //poll线程的数量(根据CPU核数配置)
        'worker_num' => 4,    //同时运行的进程数目(可配置CPU核数的1-4倍)
        'backlog' => 128,   //最大握手排队数量
        'max_request' => 1000, //此参数表示worker进程在处理完n次请求后结束运行。manager会重新创建一个worker进程。此选项用来防止worker进程内存溢出。
        'daemonize' => 1, //是否开启守护进程，开启后输出到屏幕的打印将会写入runtime/tmp/running.tmp中
        'dispatch_mode' => 2, //通道分配模式，请参考swoole文档dispatch_mode配置
        'enable_child' => 1 //是否开启内部定时器（定时器会默认清理过期的内存表数据，如果使用了内存表建议开启）
    ],
    'WEBSOCKET' => [
        'is_enable' => 0, //是否开启websocket
        'max_connections' => 10, //最大连接数 2的10次方(1024)
        'heartbeat_idle_time' => 600, //一个连接如果600秒内未向服务器发送任何数据，此连接将被强制关闭
        'heartbeat_check_interval' => 60, //心跳检测频率,表示每60秒遍历一次
        'data_type' => 'string', //message接收到的数据类型 string|json
        'heartbeat' => 1 //心跳类型：1-自动PING(PONG)心跳 2-手动心跳 0-关闭心跳
    ]
]
```

### 2. 缓存配置
```php
return [
    'CACHE' => [
        'DRIVE' => 'FILETMP', //缓存驱动 FILETMP-文件缓存、MEMCACHE-memcache缓存、MEMCACHED-memcached缓存、REDIS-redis缓存
        'PORT' => null, //网络地址端口
        'TIMEOUT' => 24 * 3600, //缓存的默认超时时间(s)
        'MAX_SIZE' => 2 * 1024, //单个文件的最大尺寸限制(kb)
        'PREFIX' => 'cache_' //缓存前缀
    ],
    'SESSION' => [
        'AUTO_START' => true, //是否自动开启session，设置为false后需要手动调用session('[START]')
        'DOMAIN' => '', //sessid的cookie配置，可在此配置跨子域名使用session
        'PATH' => '/', //sessid的cookie配置
        'EXPIRE' => 180 * 60, //session到期时间单位(秒)
        'CLEANUP' => 60, //session过期清理频率(秒)
        'DRIVE' => 'TABLE' //session驱动 TABLE-内存表、REDIS-redis驱动
    ],
    //文件缓存
    'FILETMP' => [
        'path' => TMP_PATH
    ],
    //memcache缓存
    'MEMCACHE' => [
        'host' => null,
        'port' => '11211'
    ],
    //memcached缓存
    'MEMCACHED' => [
        'host' => null,
        'port' => '11211',
        'user' => null,
        'pass' => null
    ],
    //redis缓存
    'REDIS' => [
        'host' => null,
        'port' => '6379',
        'pass' => null //配置auth权限口令
    ]
];
```

### 3. 内存表配置
```php
//[默认配置]
return [
    'MEMORY_TABLE' => [
        '__SESSION' => [ //表名
            '__total' => 10, //内存表总行数(2的指数), 2的10次方等于1024
            '__expire' => 3 * 60 * 60, //有效期3小时
            'timeout' => 'int(4)', //字段名
            'data' => 'string(10240)' //字段名 长度最大10KB
        ],
        '__PROCESS' => [
            '__total' => 10,
            '__expire' => 0, //有效期无限制
            'id' => 'int(1)',
            'type' => 'int(1)', //进程类型 0-系统 1-管理进程 2-工作进程 3-任务进程
            'pid' => 'int(2)', //进程编号
            'receive' => 'int(8)', //进程接收数据包数量
            'sendout' => 'int(8)', //进程发送数据包数量
            'memory_usage' => 'int(8)', //内存占用
            'memory_used' => 'int(8)' //内存实际使用
        ],
        '__LOCK' => [
            '__total' => 10,
            '__expire' => 60, //有效期60秒
            'type' => 'int(1)', //锁类型, 0-自旋锁 1-异步锁
            'timeout' => 'int(4)' //锁的失效时间，到时间会自动解锁
        ]
    ]
];

/**
 * [自定义配置]
 * 在高并发应用场景下，可以适量提升内存表容量
 */
return [
    'MEMORY_TABLE' => [
        '__SESSION' => [ //表名
            '__total' => 18, //2的18次方等于262144，即3小时内可以接受20W+次用户会话
            'data' => 'string(10 * 1024 * 1024)' //长度最大10MB，即每次会话session中可以存储数据量在10M以内
        ],
        '__LOCK' => [
            '__total' => 12, //2的12次方等于4096，即可以同时上锁4K多次
            '__expire' => 100 //有效期100秒，每次锁的有效期将扩大到100秒才会自动失效
        ]
    ]
];
```
- 要按照服务器内存来适量配置，否则可能会无法申请到足够的内存而启动失败

### 4. 定时器配置
本定时器是由子进程进行定时驱动，随实例的启动而启动，需要开启['SERVER']['enable_child'] => 1 启用子进程
间隔配置方式参考了linux crontab，简单实用。
```php
return [
    'TIMER' => [
        //[示例]定时统计在线人数
        //key: 模块名/控制器名/方法名
        'home/timer/getOnline' => [
            'timeout' => 0, //设置定时器失效时间(时间戳) 0为永久有效
            'interval' => '1 * * *', //时间间隔(秒 分 时 天) 本示例每分钟执行一次
            'repeat' => -1, //重复次数 -1无限重复
            'get' => [
                //定时访问[模块名/控制器名/方法名]时，携带的GET参数
                'key' => HASH_KEY // HASH_KEY:实例启动时生成的唯一字串
            ],
            'post' => [
                //定时访问[模块名/控制器名/方法名]时，携带的POST参数
            ]
        ]
    ]
];

//[apps/home/controller/timer.class.php]
//...
    //定时统计在线人数
    public function getOnline(){
        //做KEY值验证，避免被非法访问
        if(I('get.key') != HASH_KEY)return false;
        
        //...业务逻辑代码...
    }
//...
```

### 5. 异步任务配置
异步任务投递，不会影响到业务逻辑工作进程
```php
return [
    'TASK' => [
        //[示例]异步下载图片
        'autodown' => [
            'task_fun' => 'globals/task/picsDownload',
            'process_num' => 2 //进程数量,默认1
        ]
    ]
];

//调用：
$rs = M('pics')->where(['path' => ['like', 'http%']])->select();
if(!empty($rs)){
    foreach($rs as $row){
        $data = [
            'pic_id' => $row['id'],
            'path' => $row['path']
        ];
        task('autodown', $data);
    }
}

//[apps/globals/controller/task.class.php]
//...
    //下载图片
    public function picsDownload($data)
    {
        if(!empty($data['pic_id']) && !empty($data['path'])){
            if($newname = getPicAndSave($data['path'])){
                M('pics')->where(['id' => $data['pic_id']])->update(['path' => $newname]);
                return true;
            }
        }
        return false;
    }
//...
```

### 6. 锁定加载文件配置
MAP_TYPE默认配置0，在实例启动时自动加载所有[apps]中的业务逻辑代码，并常驻内存。在某些场景下，希望率先加载部分中间件类库，再加载业务逻辑代码时，可以配置1，然后在MAPS中配置需要率先加载的类库文件。
但在统一脚本代码涵盖多实例时，不是所有实例都要加载完全部脚本文件，则可以将MAP_TYPE设置为2，并用MAPS配置来区分某实例只加载哪些文件。
```php
return [
    //类库表类型 0-按目录文件自动加载 1-率先按顺序加载表中的文件 2-只按顺序加载表中的文件
    'MAP_TYPE' => 1,
    //[示例]率先加载http和websocket的中间件类库
    'MAPS' => [
        'Home\Common\MainController' => 'apps/home/common/main.controller.class.php',
        'Home\Common\MainWebsocket' => 'apps/home/common/main.websocket.class.php'
    ]
];
```

### 7. 其他配置
```php
return [
    //在用户访问实例时将会在[apps/runtime/log/模块名/]下产生大量日志文件
    'LOG' => [
        'split' => 'd', //按多长时间来分割 i-分钟 h-小时 d-天 w-周 m-月 留空则不分割
        'keep' => 7, //保留最近的7份日志,多余的自动删除,0则表示不删除
        'errorfile' => 'xml', //异常日志输出形式, xml或json
        'errortype' => [E_ERROR,E_WARNING,E_PARSE,E_NOTICE,E_CORE_ERROR,E_CORE_WARNING,E_COMPILE_ERROR,E_COMPILE_WARNING,E_USER_ERROR,E_USER_WARNING,E_USER_NOTICE,E_STRICT,E_RECOVERABLE_ERROR] //日志输出限制
    ],
    //配置时区
    'TIMEZONE' => 'Asia/Shanghai',
    //用于进行可逆加密的密码字典，字典由[0-9/a-z/A-Z]62个不重复字符组成，开发者可以根据需要随机打乱62个字符作为自己的专属密码字典
    'KEYT' => 'sXODQpGzexIwo8gJqdEj94ZFPc2KNUC3kBaTmMSL07r6u15yYnHifVlWbtvhAR',
];

//加密演示
$key = '9c4RZbuE';
$str = \Root\Util\Crypt::encode('123456',$key);
echo $str, PHP_EOL; //pH8ZPskupvJa
$str = \Root\Util\Crypt::decode($str, $key);
echo $str, PHP_EOL; //123456
```

### 8. 自定义配置
在业务目录中的配置是可以有机覆盖底层配置的，因此不必修改底层配置文件！

----------
## 函数说明

SSF为开发者提供了许多简单便捷的全局函数。开发者也可以自行在[apps/common/util/]下添加自定义函数库，函数库文件需以[.fun.php]结尾命名，系统则会自动检测并加载函数库文件到常驻内存中，如tool.fun.php。
- 如果配置了 MAP_TYPE 为 2，系统是不会自动加载该函数库文件的。

### 1. M(string $tableName, [string $dbConfName]) 数据模型函数
- $tableName 去掉前缀配置的数据表名称
- $dbConfName 数据库配置的键名，默认DB_CONF
- 返回Model实例
```php
//...[示例:带分页的用户列表]
    public function getUserList()
    {
        //从GET方式中获取分页页码，如果没有传递则默认赋值第一页
        $page = I('get.page', 1);
        //设置每页显示行数
        $pagecount = 10;
        //获取会话用户的权限ID
        $auth_id = session('user.auth');
        //从ucenter配置库的users表中查询数据
        $rs = M('users U', 'ucenter')
            //选择users表中需要输出的字段  
            ->field(['id', 'username', 'sex'], 'U')
            //选择pictures表中将要输出的字段，并为path设置输出别名headpic
            ->field(['path' => 'headpic'], 'P')
            //左联方式关联ucenter配置库中的pictures表
            ->join('pictures P', ['U.pic_id' => 'P.id'], 'left')
            //查询筛选
            ->where(['U.is_exist' => 1, 'U.type' => ['in', [1,2,3]]])
            //可以多次调用查询筛选，最终会以and方式自动合并
            ->where(['U.auth' => [['like', $auth_id . ',%'], ['like', '%,' . $auth_id], ['like', '%,'. $auth_id .',%'], 'or']])
            //以users表中的id字段的倒序排列
            ->order('U.id', 'desc')
            //分页
            ->limit(($page-1)*$pagecount, $pagecount)
            //最终查询
            ->select();
        //将数据输出到页面的$userlist变量中
        $this->assign('userlist', $rs);
        //加载本模块/本控制器名/本方法名的视图
        $this->display();
    }
//...
```

### 2. D(string $tableName, [string $dbConfName]) 自定义数据模型函数
- $tableName 自定义模型名称或去掉前缀配置的数据表名称
- $dbConfName 数据库配置的键名，默认DB_CONF
- 返回Model实例
> 与M()最大的区别是可以加载自定义数据模型，目前支持的数据模型底层除原生Model外还有ViewModel和RelationModel(试验版)两种。
```php
//示例：用户数据模型[apps/home/model/user.view.model.class.php]
<?php
namespace Home\Model;
use Root\Model\ViewModel;

class UserViewModel extends ViewModel
{
    protected $viewFields = [
        'A' => [
            'table' => 'Users', //表名和字段名都是忽略大小写的
            'field' => ['id','nickname' => 'name','sex','birth','is_exist','datetime']
        ],
        'B' => [
            'table' => 'Citys',
            'field' => ['name' => 'city_name'],
            'on' => ['A.city_id' => 'B.id']
        ],
        'C' => [
            'table' => 'Citys',
            'field' => ['name' => 'province_name'], //最常见的省市同表关联
            'on' => ['B.pid' => 'C.id']
        ],
        'D' => [
            'table' => 'UserScore', //相当于 xx_user_score 表(xx_是前缀)
            'field' => ['sum(value)' => 'sum_value'], //可以使用mysql集合函数
            'on' => ['A.id' => 'D.user_id'],
            'type' => 'left', //左联
            'group' => 'user_id'
        ]
    ];
}

//在同模块[home]中的使用方式
//...
         $rs = D('UserView')->where(['is_exist' => 1])->limit(1,30)->select();
//...
```

### 3. I(string $name, [$default = false]) 通用输入函数
- $name 要采集的输入索引
- $default 如果不存在则赋予的默认值
- 反馈采集到的输入数据

允许采集8种输入数据：

| 索 引 | 注 解 |
| :---: | :-----------------------------: |
| get.* | 采集以GET方式传递的数据，相当于 $_GET |
| post.* | 采集以POST方式传递的数据，相当于 $_POST |
| cookie.* | 采集以COOKIE形式传递的数据，相当于 $_COOKIE |
| server.* | 采集服务器信息和请求的头部数据，相当于 $_SERVER |
| files.* | 采集以FILES传递的数据，相当于 $_FILES |
| header.* | 采集请求的头部数据 |
| request.* | 采集以GET/POST方式传递的数据，相当于 $_REQUEST |
| input | 采集原始的POST包体，用于非application/x-www-form-urlencoded格式的Http POST请求。 |
```php
//示例
$username = I('post.username', 'admin');
$password = I('post.password');
$type = I('get.type', 1);
$sessid = I('cookie.phpsessid'); //请求的键名是忽略大小写的
$files = I('files.'); //以名值对形式返回所有$_FILES
$ip = I('server.remote_addr', '127.0.0.1');
```

### 4. C(string $key, [$val = null]) 获取配置数据
- $key 配置索引键名
- $val 为某键赋值
- 返回获取到的配置数据
```php
//示例`注意大小写`
$sess_count = C('MEMORY_TABLE.__SESSION.__total');
```

### 5. session(string $key, [$val = [NULL]]) SESSION处理函数
- $key string-session键名|[START]-开启session|[ID]-获取sessionid|[HAS]-判断KEY值是否存在|[CLEAR]-清空session
- $val [NULL]-获取session值|string-赋予字符串值|array-赋予数组值
- 返回对应的session数据|null-删除对应session
> session()只能用于会话期，会话结束session()将失效，谨慎使用$_SESSION全局变量，多协程下此变量取值会异常
```php
//示例手动开启session，配置文件中可以配置自动开启
$token = I('get.token');
//判断token是否有效
if (!empty($token)) {
    if (!session('[HAS]', $token)) {
        $this->error('TOKEN不存在!!!!');
        return false;
    }
    //使用token开启session
    session('[START]', $token);
} else {
    $this->getToken();
    return false;
}
//判断是否登录
if(session('?user'))$user = session('user');
```

### 6. cookie(string $key, string $val = '[NULL]', int $expire = null) COOKIE处理函数
- $key string-cookie键名
- $val [NULL]-获取cookie值|string-赋予字符串值|array-赋予数组值
- $expire int-过期时间|null-默认会话期
- 返回对应的cookie数据|null-删除对应cookie
> session()只能用于会话期，会话结束session()将失效，谨慎使用$_SESSION全局变量，多协程下此变量取值会异常
```php
//示例自动登录失败删除cookie
if(!$this->>login(cookie('username'), cookie('passkey'))){
    cookie('username', null);
    cookie('passkey', null);
}
```

### 7. L($msg, $prefix = 'user', $dirname = null) 记录日志
- $msg 要记录的日志，如非字符串则自动加上var_export($msg, true)
- $prefix 日志文件前缀
- $dirname 日志文件保存的目录（日志文件统一保存在app/runtime/log/下）
```php
//示例
$class = new Class();
L($class, 'class');
```

-----------
更多WIKI请跳转：https://github.com/ljj7410/Simple-Swoole-Framework/wiki